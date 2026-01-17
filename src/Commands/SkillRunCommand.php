<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Skills\SkillRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'skill:run',
    description: 'Execute a skill',
)]
class SkillRunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('skill', InputArgument::OPTIONAL, 'Skill identifier')
            ->addOption('params', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Parameters as key=value', [])
            ->addOption('json', null, InputOption::VALUE_OPTIONAL, 'Parameters as JSON string')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List available skills')
            ->addOption('info', 'i', InputOption::VALUE_NONE, 'Show skill info');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registry = $this->getSkillRegistry();

        // List skills
        if ($input->getOption('list')) {
            return $this->listSkills($output, $registry);
        }

        $skillId = $input->getArgument('skill');

        // Interactive skill selection
        if (! $skillId) {
            $skills = $registry->all();
            if (empty($skills)) {
                warning('No skills registered.');

                return self::FAILURE;
            }

            $options = [];
            foreach ($skills as $id => $skill) {
                $options[$id] = "{$skill->name()} - {$skill->description()}";
            }

            $skillId = select(
                label: 'Select a skill to run',
                options: $options,
            );
        }

        $skill = $registry->get($skillId);
        if (! $skill) {
            $output->writeln("<error>Skill not found: {$skillId}</error>");
            $output->writeln('');
            $output->writeln('Available skills:');
            foreach ($registry->all() as $id => $s) {
                $output->writeln("  - {$id}: {$s->name()}");
            }

            return self::FAILURE;
        }

        // Show skill info
        if ($input->getOption('info')) {
            return $this->showSkillInfo($output, $skill);
        }

        // Parse parameters
        $params = $this->parseParams($input, $skill, $output);
        if ($params === null) {
            return self::FAILURE;
        }

        // Execute the skill
        $output->writeln('');
        info("Executing skill: {$skill->name()}");

        $result = $skill->execute($params);

        if ($result->isSuccess()) {
            info('Skill executed successfully!');

            // Show output
            if ($result->output()) {
                $output->writeln('');
                $output->writeln('<comment>Output:</comment>');
                if (is_array($result->output())) {
                    foreach ($result->output() as $item) {
                        $output->writeln("  - {$item}");
                    }
                } else {
                    $output->writeln("  {$result->output()}");
                }
            }

            // Show artifacts
            $artifacts = $result->artifacts();
            if (! empty($artifacts)) {
                $output->writeln('');
                $output->writeln('<comment>Artifacts:</comment>');
                foreach ($artifacts as $key => $value) {
                    if (is_string($value)) {
                        $output->writeln("  {$key}: {$value}");
                    }
                }
            }

            // Show next steps
            $nextSteps = $result->nextSteps();
            if (! empty($nextSteps)) {
                $output->writeln('');
                $output->writeln('<comment>Suggested next steps:</comment>');
                foreach ($nextSteps as $step) {
                    $output->writeln("  - {$step['skill']}: {$step['reason']}");
                }
            }

            return self::SUCCESS;
        }

        $output->writeln("<error>Skill failed: {$result->error()}</error>");

        return self::FAILURE;
    }

    private function listSkills(OutputInterface $output, SkillRegistry $registry): int
    {
        $skills = $registry->all();

        if (empty($skills)) {
            warning('No skills registered.');

            return self::SUCCESS;
        }

        $output->writeln('');
        info('Available Skills:');
        $output->writeln('');

        $categories = $registry->categories();
        foreach ($categories as $category) {
            $categorySkills = $registry->byCategory($category);
            if (empty($categorySkills)) {
                continue;
            }

            $output->writeln("<comment>{$category}:</comment>");
            foreach ($categorySkills as $id => $skill) {
                $output->writeln("  <info>{$id}</info> - {$skill->description()}");
            }
            $output->writeln('');
        }

        return self::SUCCESS;
    }

    private function showSkillInfo(OutputInterface $output, $skill): int
    {
        $output->writeln('');
        info("Skill: {$skill->name()}");
        $output->writeln('');
        $output->writeln("<comment>Identifier:</comment> {$skill->identifier()}");
        $output->writeln("<comment>Category:</comment> {$skill->category()}");
        $output->writeln("<comment>Description:</comment> {$skill->description()}");

        $tags = $skill->tags();
        if (! empty($tags)) {
            $output->writeln('<comment>Tags:</comment> '.implode(', ', $tags));
        }

        $params = $skill->parameters();
        if (! empty($params)) {
            $output->writeln('');
            $output->writeln('<comment>Parameters:</comment>');
            foreach ($params as $name => $spec) {
                $required = ($spec['required'] ?? false) ? ' (required)' : '';
                $default = isset($spec['default']) ? ' [default: '.json_encode($spec['default']).']' : '';
                $output->writeln("  <info>{$name}</info>: {$spec['description']}{$required}{$default}");
            }
        }

        return self::SUCCESS;
    }

    private function parseParams(InputInterface $input, $skill, OutputInterface $output): ?array
    {
        $params = [];

        // Parse JSON params
        $jsonParams = $input->getOption('json');
        if ($jsonParams) {
            $decoded = json_decode($jsonParams, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $output->writeln('<error>Invalid JSON parameters</error>');

                return null;
            }
            $params = $decoded;
        }

        // Parse key=value params
        foreach ($input->getOption('params') as $param) {
            if (str_contains($param, '=')) {
                [$key, $value] = explode('=', $param, 2);
                // Try to decode JSON values
                $decoded = json_decode($value, true);
                $params[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }
        }

        // Interactive prompts for required params
        foreach ($skill->parameters() as $name => $spec) {
            if (($spec['required'] ?? false) && ! isset($params[$name])) {
                $params[$name] = text(
                    label: $spec['description'],
                    required: true,
                );
            }
        }

        return $params;
    }

    private function getSkillRegistry(): SkillRegistry
    {
        $registry = new SkillRegistry($this->laraforge);

        // Register core skills
        $this->registerCoreSkills($registry);

        return $registry;
    }

    private function registerCoreSkills(SkillRegistry $registry): void
    {
        // Generator Skills
        $registry->register(new \LaraForge\Skills\GeneratorSkills\ApiResourceSkill);
        $registry->register(new \LaraForge\Skills\GeneratorSkills\PolicySkill);
        $registry->register(new \LaraForge\Skills\GeneratorSkills\ManagerSkill);
        $registry->register(new \LaraForge\Skills\GeneratorSkills\FeatureTestSkill);

        // Document Skills
        $registry->register(new \LaraForge\Skills\DocumentSkills\CreatePrdSkill);
        $registry->register(new \LaraForge\Skills\DocumentSkills\CreateFrdSkill);
        $registry->register(new \LaraForge\Skills\DocumentSkills\CreatePseudocodeSkill);
        $registry->register(new \LaraForge\Skills\DocumentSkills\CreateTestContractSkill);

        // Git Skills
        $registry->register(new \LaraForge\Skills\GitSkills\BranchSkill);
        $registry->register(new \LaraForge\Skills\GitSkills\WorktreeSkill);
        $registry->register(new \LaraForge\Skills\GitSkills\CommitSkill);
        $registry->register(new \LaraForge\Skills\GitSkills\MergeSkill);
    }
}
