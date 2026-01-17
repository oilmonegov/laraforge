<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Commands\Concerns\SuggestsNextStep;
use LaraForge\Documents\DocumentRegistry;
use LaraForge\Documents\Parsers\ExternalPrdParser;
use LaraForge\Documents\ProductRequirements;
use LaraForge\Project\Feature;
use LaraForge\Project\ProjectState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'prd:import',
    description: 'Import an external PRD file into the project',
)]
class PrdImportCommand extends Command
{
    use SuggestsNextStep;

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the PRD file to import')
            ->addOption('feature', 'f', InputOption::VALUE_OPTIONAL, 'Associate with a feature ID')
            ->addOption('normalize', null, InputOption::VALUE_NONE, 'Normalize to structured YAML format')
            ->addOption('title', 't', InputOption::VALUE_OPTIONAL, 'Override the PRD title')
            ->addOption('copy-only', null, InputOption::VALUE_NONE, 'Only copy the file without parsing (keeps original format)')
            ->addOption('create-feature', null, InputOption::VALUE_NONE, 'Create a new feature from the PRD');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $workingDir = $this->laraforge->workingDirectory();
        $filesystem = new Filesystem;

        // Resolve file path
        if (! str_starts_with($filePath, '/')) {
            $filePath = $workingDir.'/'.$filePath;
        }

        // Validate file exists
        if (! $filesystem->exists($filePath)) {
            error("File not found: {$filePath}");

            return self::FAILURE;
        }

        // Load or initialize project state
        $state = ProjectState::load($workingDir);
        if (! $state) {
            $projectName = basename($workingDir);
            $state = ProjectState::initialize($workingDir, $projectName);
            info("Initialized LaraForge project: {$projectName}");
        }

        $docsDir = $workingDir.'/.laraforge/docs';

        // Read the file content
        $content = file_get_contents($filePath);
        if ($content === false) {
            error("Cannot read file: {$filePath}");

            return self::FAILURE;
        }

        // Handle copy-only mode
        if ($input->getOption('copy-only')) {
            return $this->copyOnly($filePath, $docsDir, $filesystem, $output);
        }

        // Parse the PRD
        $parser = new ExternalPrdParser;

        if (! $parser->canParse($content)) {
            error('Cannot parse this file format.');

            return self::FAILURE;
        }

        $errors = $parser->validateContent($content);
        if (! empty($errors)) {
            error('Validation errors:');
            foreach ($errors as $err) {
                $output->writeln("  - {$err}");
            }

            return self::FAILURE;
        }

        /** @var ProductRequirements $prd */
        $prd = $parser->parse($content, $filePath);

        // Override title if provided
        if ($title = $input->getOption('title')) {
            $prd->setTitle($title);
        }

        // Interactive title if still generic
        if ($prd->title() === 'Imported PRD' || empty($prd->title())) {
            $prd->setTitle(text(
                label: 'PRD Title',
                placeholder: 'User Authentication',
                required: true,
            ));
        }

        // Show extraction summary
        $this->showExtractionSummary($prd, $output);

        // Ask user to confirm or edit
        if (! $input->getOption('normalize')) {
            $action = select(
                label: 'How do you want to import this PRD?',
                options: [
                    'normalize' => 'Normalize to structured YAML (recommended)',
                    'copy' => 'Copy as-is (keep original format)',
                    'edit' => 'Review extracted data before saving',
                ],
                default: 'normalize',
            );

            if ($action === 'copy') {
                return $this->copyOnly($filePath, $docsDir, $filesystem, $output);
            }

            if ($action === 'edit') {
                $prd = $this->interactiveEdit($prd);
            }
        }

        // Validate the structured PRD
        if (! $prd->isValid()) {
            $validationErrors = $prd->validationErrors();
            warning('PRD has validation issues:');
            foreach ($validationErrors as $err) {
                $output->writeln("  - {$err}");
            }

            if (! confirm('Continue anyway?', false)) {
                return self::FAILURE;
            }
        }

        // Save the PRD
        $registry = new DocumentRegistry($docsDir);
        $savedPath = $registry->save($prd);

        info("PRD imported successfully: {$savedPath}");

        // Handle feature association
        $featureId = $input->getOption('feature');
        $createFeature = $input->getOption('create-feature');

        if ($createFeature || (! $featureId && confirm('Create a new feature from this PRD?', true))) {
            $feature = Feature::create($prd->title());
            $feature->addDocument('prd', $savedPath);
            $feature->setPhase('requirements');
            $state->addFeature($feature);
            $state->setCurrentFeature($feature->id());

            info("Created feature: {$feature->title()}");
            note("Feature ID: {$feature->id()}");
        } elseif ($featureId) {
            $feature = $state->feature($featureId);
            if ($feature) {
                $feature->addDocument('prd', $savedPath);
                $state->updateFeature($feature);
                info("Associated PRD with feature: {$feature->title()}");
            } else {
                warning("Feature not found: {$featureId}");
            }
        }

        // Mark 'import-prd' step complete and suggest what's next
        $this->completeStepAndSuggestNext('import-prd', $output);

        return self::SUCCESS;
    }

    private function copyOnly(string $sourcePath, string $docsDir, Filesystem $filesystem, OutputInterface $output): int
    {
        $filename = basename($sourcePath);
        $destPath = $docsDir.'/prd/'.$filename;

        // Ensure directory exists
        $prdDir = $docsDir.'/prd';
        if (! $filesystem->exists($prdDir)) {
            $filesystem->mkdir($prdDir, 0755);
        }

        $filesystem->copy($sourcePath, $destPath, true);
        info("PRD copied to: {$destPath}");

        // Mark step complete and suggest what's next
        $this->completeStepAndSuggestNext('import-prd', $output);

        return self::SUCCESS;
    }

    private function showExtractionSummary(ProductRequirements $prd, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Extracted structure:</comment>');

        $rows = [
            ['Title', $prd->title()],
            ['Problem Statement', $prd->problemStatement() ? 'Yes ('.strlen($prd->problemStatement()).' chars)' : 'No'],
            ['Target Audience', $prd->targetAudience() ? 'Yes' : 'No'],
            ['Objectives', (string) count($prd->objectives())],
            ['Requirements', (string) count($prd->requirements())],
            ['User Stories', (string) count($prd->userStories())],
            ['Constraints', (string) count($prd->constraints())],
            ['Assumptions', (string) count($prd->assumptions())],
            ['Out of Scope', (string) count($prd->outOfScope())],
            ['Success Criteria', (string) count($prd->successCriteria())],
        ];

        table(['Field', 'Value'], $rows);
    }

    private function interactiveEdit(ProductRequirements $prd): ProductRequirements
    {
        // Title
        $title = text(
            label: 'PRD Title',
            default: $prd->title(),
            required: true,
        );
        $prd->setTitle($title);

        // Problem Statement
        if (confirm('Edit problem statement?', false)) {
            $statement = text(
                label: 'Problem Statement',
                default: $prd->problemStatement() ?? '',
            );
            if (! empty($statement)) {
                $prd->setProblemStatement($statement);
            }
        }

        // Review objectives
        if (count($prd->objectives()) > 0 && confirm('Review objectives? ('.count($prd->objectives()).' found)', false)) {
            info('Current objectives:');
            foreach ($prd->objectives() as $i => $obj) {
                echo '  '.($i + 1).". [{$obj['priority']}] {$obj['description']}\n";
            }
        }

        // Add missing objective if none
        if (count($prd->objectives()) === 0) {
            warning('No objectives found. At least one objective is required.');
            $objDesc = text(
                label: 'Add an objective',
                placeholder: 'Implement the feature as specified in the requirements',
                required: true,
            );
            $prd->addObjective('OBJ-1', $objDesc, 'high');
        }

        return $prd;
    }
}
