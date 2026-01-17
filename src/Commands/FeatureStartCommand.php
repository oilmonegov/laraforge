<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Commands\Concerns\SuggestsNextStep;
use LaraForge\Guide\WorkflowGuide;
use LaraForge\Guide\WorkflowType;
use LaraForge\Project\Feature;
use LaraForge\Project\ProjectState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(
    name: 'feature:start',
    description: 'Start a new feature workflow',
)]
class FeatureStartCommand extends Command
{
    use SuggestsNextStep;

    protected function configure(): void
    {
        $this
            ->addArgument('title', InputArgument::OPTIONAL, 'Feature title')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'Feature description')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Feature priority (1-5)', '3')
            ->addOption('no-prd', null, InputOption::VALUE_NONE, 'Skip PRD creation prompt')
            ->addOption('no-branch', null, InputOption::VALUE_NONE, 'Skip branch creation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();

        // Load or initialize project state
        $state = ProjectState::load($workingDir);
        if (! $state) {
            $projectName = basename($workingDir);
            $state = ProjectState::initialize($workingDir, $projectName);
            info("Initialized LaraForge project: {$projectName}");
        }

        // Get feature title
        $title = $input->getArgument('title');
        if (! $title) {
            $title = text(
                label: 'Feature title',
                placeholder: 'User Authentication',
                required: true,
            );
        }

        // Get description
        $description = $input->getOption('description');
        if (! $description) {
            $description = text(
                label: 'Feature description (optional)',
                placeholder: 'Implement user authentication with OAuth support',
            );
        }

        // Get priority
        $priority = (int) $input->getOption('priority');
        if ($priority < 1 || $priority > 5) {
            $priority = (int) select(
                label: 'Feature priority',
                options: [
                    '1' => '1 - Critical',
                    '2' => '2 - High',
                    '3' => '3 - Medium',
                    '4' => '4 - Low',
                    '5' => '5 - Nice to have',
                ],
                default: '3',
            );
        }

        // Create the feature
        $feature = Feature::create($title, $description ?? '', $priority);
        $state->addFeature($feature);
        $state->setCurrentFeature($feature->id());

        info("Created feature: {$feature->title()}");
        note("Feature ID: {$feature->id()}");

        // Start the feature workflow in the guide
        $guide = new WorkflowGuide($workingDir);
        $guide->startWorkflow(WorkflowType::FEATURE, $title);

        // Suggest what's next
        $this->showNextStepSuggestion($guide, $output);

        return self::SUCCESS;
    }
}
