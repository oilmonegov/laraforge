<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Commands\Concerns\SuggestsNextStep;
use LaraForge\Guide\GuideStep;
use LaraForge\Guide\WorkflowGuide;
use LaraForge\Guide\WorkflowType;
use LaraForge\Session\SessionConflict;
use LaraForge\Session\SessionManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'next',
    description: 'Show and run the next step in your workflow',
)]
class NextCommand extends Command
{
    use SuggestsNextStep;

    protected function configure(): void
    {
        $this
            ->addOption('skip', 's', InputOption::VALUE_NONE, 'Skip the current step (if optional)')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all remaining steps')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Show current progress status')
            ->addOption('start', null, InputOption::VALUE_REQUIRED, 'Start a new workflow (feature, bugfix, refactor, hotfix)')
            ->addOption('end', null, InputOption::VALUE_NONE, 'End the current workflow')
            ->addOption('history', null, InputOption::VALUE_NONE, 'Show workflow history');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $guide = new WorkflowGuide($workingDir);
        $sessionManager = new SessionManager($workingDir);

        // Clean up stale sessions
        $sessionManager->cleanupStaleSessions();

        // Check for session conflicts (parallel agents on same branch)
        $conflict = $sessionManager->detectConflict();
        if ($conflict !== null) {
            return $this->handleSessionConflict($sessionManager, $conflict, $output);
        }

        // Start/update session tracking
        $workflowType = $guide->currentWorkflowType();
        $sessionManager->startSession(
            $workflowType?->value,
            $guide->workflowName()
        );

        // Handle start new workflow
        if ($startType = $input->getOption('start')) {
            return $this->startNewWorkflow($guide, $startType, $output, $workingDir);
        }

        // Handle end workflow
        if ($input->getOption('end')) {
            return $this->endWorkflow($guide, $sessionManager, $output);
        }

        // Handle history
        if ($input->getOption('history')) {
            return $this->showHistory($guide, $output);
        }

        // Handle status (non-interactive)
        if ($input->getOption('status')) {
            $this->showFullStatus($guide, $output);

            return self::SUCCESS;
        }

        // Handle list (non-interactive)
        if ($input->getOption('list')) {
            return $this->listRemainingSteps($guide, $output);
        }

        // Handle skip
        if ($input->getOption('skip')) {
            $currentStep = $guide->currentStep();
            if ($currentStep) {
                return $this->skipStepDirectly($guide, $currentStep, $output, $workingDir);
            }
        }

        // Check if we have an active workflow
        $workflowType = $guide->currentWorkflowType();

        if ($workflowType === null) {
            return $this->promptToStartWorkflow($guide, $output, $workingDir);
        }

        // Use the interactive suggestion system
        $this->promptNextAction($guide, $output, $workingDir);

        return self::SUCCESS;
    }

    private function startNewWorkflow(
        WorkflowGuide $guide,
        string $typeString,
        OutputInterface $output,
        string $workingDir,
    ): int {
        $type = WorkflowType::tryFrom($typeString);

        if ($type === null) {
            warning("Unknown workflow type: {$typeString}");
            $output->writeln('Available types: feature, bugfix, refactor, hotfix');

            return self::FAILURE;
        }

        // Ask for workflow name
        $name = text(
            label: "What's the name of this {$type->label()}?",
            placeholder: match ($type) {
                WorkflowType::FEATURE => 'User Authentication',
                WorkflowType::BUGFIX => 'Fix login redirect issue',
                WorkflowType::REFACTOR => 'Refactor user service',
                WorkflowType::HOTFIX => 'Fix production crash',
                WorkflowType::ONBOARDING => 'Project Setup',
            },
            required: true,
        );

        $guide->startWorkflow($type, $name);

        info("{$type->icon()} Started: {$name}");

        // Use interactive suggestion for first step
        $this->promptNextAction($guide, $output, $workingDir);

        return self::SUCCESS;
    }

    private function endWorkflow(
        WorkflowGuide $guide,
        SessionManager $sessionManager,
        OutputInterface $output,
    ): int {
        $type = $guide->currentWorkflowType();

        if ($type === null) {
            warning('No active workflow to end.');

            return self::FAILURE;
        }

        $name = $guide->workflowName() ?? $type->label();

        $options = [
            'end' => "End workflow \"{$name}\"",
            'cancel' => 'Cancel',
        ];

        $action = select(
            label: "End the current {$type->label()} workflow?",
            options: $options,
            default: 'end',
        );

        if ($action === 'end') {
            $guide->endWorkflow();
            $sessionManager->endSession();
            info("Workflow '{$name}' completed!");
            $output->writeln('');
            note('Run `laraforge next` to start a new workflow.');
        }

        return self::SUCCESS;
    }

    private function promptToStartWorkflow(
        WorkflowGuide $guide,
        OutputInterface $output,
        string $workingDir,
    ): int {
        $output->writeln('');

        if (! $guide->isProjectInitialized()) {
            // First time - start onboarding
            info('Welcome to LaraForge!');
            $output->writeln('Let\'s set up your project.');
            $output->writeln('');

            $guide->startWorkflow(WorkflowType::ONBOARDING, 'Project Setup');

            // Use interactive suggestion
            $this->promptNextAction($guide, $output, $workingDir);

            return self::SUCCESS;
        }

        // Project exists, ask what they want to do
        info('No active workflow. What would you like to do?');
        $output->writeln('');

        $options = [];
        foreach (WorkflowType::forExistingProject() as $type) {
            $options[$type->value] = "{$type->icon()} {$type->label()} - {$type->description()}";
        }
        $options['exit'] = 'Exit';

        $choice = select(
            label: 'Select workflow type',
            options: $options,
        );

        if ($choice === 'exit') {
            return self::SUCCESS;
        }

        return $this->startNewWorkflow($guide, $choice, $output, $workingDir);
    }

    private function showHistory(WorkflowGuide $guide, OutputInterface $output): int
    {
        $history = $guide->history();

        if (empty($history)) {
            note('No workflow history yet.');

            return self::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<comment>Workflow History</comment>');
        $output->writeln(str_repeat('─', 50));

        foreach (array_reverse($history) as $entry) {
            $type = WorkflowType::tryFrom($entry['type'] ?? '');
            $icon = $type?->icon() ?? '•';
            $label = $type?->label() ?? 'Unknown';
            $name = $entry['name'] ?? '';
            $completedAt = $entry['completed_at'] ?? '';
            $steps = $entry['steps_completed'] ?? 0;

            $output->writeln("{$icon} <info>{$label}</info>: {$name}");
            $output->writeln("   <fg=gray>Completed: {$completedAt} ({$steps} steps)</>");
            $output->writeln('');
        }

        return self::SUCCESS;
    }

    private function listRemainingSteps(WorkflowGuide $guide, OutputInterface $output): int
    {
        $type = $guide->currentWorkflowType();

        if ($type === null) {
            note('No active workflow.');

            return self::SUCCESS;
        }

        $remaining = $guide->remainingSteps();

        if (empty($remaining)) {
            info('All steps completed!');

            return self::SUCCESS;
        }

        $output->writeln('');
        $output->writeln("<comment>{$type->icon()} {$type->label()} - Remaining Steps:</comment>");
        $output->writeln('');

        $currentPhase = '';
        foreach ($remaining as $step) {
            if ($step->phase !== $currentPhase) {
                $currentPhase = $step->phase;
                $output->writeln('<fg=yellow>'.ucfirst($currentPhase).':</>');
            }

            $marker = $step->required ? '<fg=red>[Required]</>' : '<fg=gray>[Optional]</>';
            $output->writeln("  {$marker} {$step->name}");
            $output->writeln("         <fg=gray>{$step->description}</>");

            if ($step->command) {
                $output->writeln("         <info>{$step->command}</info>");
            }
            $output->writeln('');
        }

        return self::SUCCESS;
    }

    private function skipStepDirectly(
        WorkflowGuide $guide,
        GuideStep $step,
        OutputInterface $output,
        string $workingDir,
    ): int {
        if ($step->required) {
            warning("Cannot skip '{$step->name}' - this step is required.");

            return self::FAILURE;
        }

        $guide->markSkipped($step->id);
        info("Skipped: {$step->name}");

        // Continue with interactive suggestion
        $this->promptNextAction($guide, $output, $workingDir);

        return self::SUCCESS;
    }

    private function handleSessionConflict(
        SessionManager $sessionManager,
        SessionConflict $conflict,
        OutputInterface $output,
    ): int {
        $output->writeln('');
        error('⚠️  Session Conflict Detected');
        $output->writeln('');

        $output->writeln("<fg=yellow>{$conflict->message}</>");
        $output->writeln('');

        $session = $conflict->conflictingSession;
        $output->writeln('<comment>Active session:</comment>');
        $output->writeln("  {$session->description()}");
        $output->writeln("  <fg=gray>Last activity: {$session->lastActivity}</>");
        $output->writeln('');

        note($conflict->suggestion);
        $output->writeln('');

        // Offer solutions
        $options = [
            'worktree' => 'Create a worktree (work in parallel safely)',
            'branch' => 'Switch to a different branch',
            'continue' => 'Continue anyway (not recommended)',
            'exit' => 'Exit and let the other session finish',
        ];

        $action = select(
            label: 'How would you like to proceed?',
            options: $options,
            default: 'worktree',
        );

        return match ($action) {
            'worktree' => $this->createWorktreeForConflict($sessionManager, $output),
            'branch' => $this->switchBranchForConflict($output),
            'continue' => self::SUCCESS,
            'exit' => self::FAILURE,
            default => self::FAILURE,
        };
    }

    private function createWorktreeForConflict(SessionManager $sessionManager, OutputInterface $output): int
    {
        $name = text(
            label: 'Worktree name',
            placeholder: 'my-parallel-work',
            default: $sessionManager->suggestWorktreeName(),
            required: true,
        );

        $worktreePath = $sessionManager->createWorktree($name);

        if ($worktreePath === null) {
            error('Failed to create worktree');

            return self::FAILURE;
        }

        info("Worktree created: {$worktreePath}");
        $output->writeln('');
        note('To work in the new worktree, run:');
        $output->writeln("  <info>cd {$worktreePath}</info>");
        $output->writeln('  <info>laraforge next</info>');

        return self::SUCCESS;
    }

    private function switchBranchForConflict(OutputInterface $output): int
    {
        $branchName = text(
            label: 'New branch name',
            placeholder: 'feature/my-work',
            required: true,
        );

        $process = \Symfony\Component\Process\Process::fromShellCommandline(
            'git checkout -b '.escapeshellarg($branchName),
            $this->laraforge->workingDirectory()
        );

        $exitCode = $process->run();

        if ($exitCode === 0) {
            info("Switched to new branch: {$branchName}");
            $output->writeln('Run <info>laraforge next</info> to continue.');

            return self::SUCCESS;
        }

        error('Failed to create branch');
        $output->writeln($process->getErrorOutput());

        return self::FAILURE;
    }
}
