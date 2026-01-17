<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Project\ProjectContext;
use LaraForge\Project\ProjectState;
use LaraForge\Skills\SkillRegistry;
use LaraForge\Workflows\FeatureWorkflow;
use LaraForge\Workflows\WorkflowOrchestrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'plan',
    description: 'Analyze project state and recommend next actions',
)]
class PlanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('feature', 'f', InputOption::VALUE_OPTIONAL, 'Feature ID to analyze')
            ->addOption('workflow', 'w', InputOption::VALUE_OPTIONAL, 'Workflow to use', 'feature')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $state = ProjectState::load($workingDir);

        if (! $state) {
            warning('No LaraForge project found.');
            $output->writeln('');
            $output->writeln('Get started with: <info>laraforge feature:start "My Feature"</info>');

            return self::SUCCESS;
        }

        // Set up context
        $featureId = $input->getOption('feature');
        $feature = $featureId ? $state->feature($featureId) : $state->currentFeature();

        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $state,
            feature: $feature,
        );

        // Set up orchestrator
        $skills = new SkillRegistry($this->laraforge);
        $orchestrator = new WorkflowOrchestrator($this->laraforge, $skills);

        // Register workflows
        $orchestrator->register(new FeatureWorkflow);

        // Analyze and recommend
        $recommendation = $orchestrator->analyze($context);

        if ($input->getOption('json')) {
            $output->writeln(json_encode($recommendation->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Display recommendation
        $output->writeln('');
        info('Project Analysis');

        if ($feature) {
            $output->writeln('');
            $output->writeln("<comment>Current Feature:</comment> {$feature->title()}");
            $output->writeln("<comment>Status:</comment> {$feature->status()}");
            $output->writeln("<comment>Phase:</comment> {$feature->phase()}");
            $output->writeln("<comment>Progress:</comment> {$feature->progress()}%");
        } else {
            note('No active feature selected');
        }

        $output->writeln('');
        $output->writeln('<comment>Recommendation:</comment>');
        $output->writeln('');
        $output->writeln("  <info>{$recommendation->message}</info>");
        $output->writeln('');
        $output->writeln("<comment>Suggested Agent:</comment> {$recommendation->agent}");
        $output->writeln("<comment>Suggested Skill:</comment> {$recommendation->skill}");

        if ($recommendation->workflow) {
            $output->writeln("<comment>Workflow:</comment> {$recommendation->workflow}");
        }
        if ($recommendation->step) {
            $output->writeln("<comment>Step:</comment> {$recommendation->step}");
        }

        // Show command to execute
        $output->writeln('');
        $output->writeln('<comment>Run this command:</comment>');
        $output->writeln("  laraforge skill:run {$recommendation->skill}");

        // Show workflow status if available
        $workflowId = $input->getOption('workflow');
        $workflow = $orchestrator->get($workflowId);

        if ($workflow) {
            $output->writeln('');
            $output->writeln('<comment>Workflow Progress:</comment>');

            $status = $workflow instanceof \LaraForge\Workflows\Workflow
                ? $workflow->status($context)
                : ['steps' => []];

            foreach ($status['steps'] ?? [] as $stepId => $stepStatus) {
                $icon = $stepStatus['completed'] ? '✓' : ($stepStatus['current'] ? '→' : '○');
                $name = $stepStatus['name'];
                $output->writeln("  {$icon} {$name}");
            }
        }

        return self::SUCCESS;
    }
}
