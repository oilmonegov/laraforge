<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Guide\GuideStep;
use LaraForge\Guide\WorkflowGuide;
use LaraForge\Guide\WorkflowType;
use LaraForge\Session\SessionManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
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
    protected function configure(): void
    {
        $this
            ->addOption('skip', 's', InputOption::VALUE_NONE, 'Skip the current step (if optional)')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all remaining steps')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Show current progress status')
            ->addOption('start', null, InputOption::VALUE_REQUIRED, 'Start a new workflow (feature, bugfix, refactor, hotfix)')
            ->addOption('end', null, InputOption::VALUE_NONE, 'End the current workflow')
            ->addOption('history', null, InputOption::VALUE_NONE, 'Show workflow history')
            ->addOption('auto', 'a', InputOption::VALUE_NONE, 'Automatically run the next command without confirmation');
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
        if ($workflowType = $input->getOption('start')) {
            return $this->startNewWorkflow($guide, $workflowType, $output);
        }

        // Handle end workflow
        if ($input->getOption('end')) {
            return $this->endWorkflow($guide, $output);
        }

        // Handle history
        if ($input->getOption('history')) {
            return $this->showHistory($guide, $output);
        }

        // Handle status
        if ($input->getOption('status')) {
            return $this->showStatus($guide, $output);
        }

        // Handle list
        if ($input->getOption('list')) {
            return $this->listRemainingSteps($guide, $output);
        }

        // Check if we have an active workflow
        $workflowType = $guide->currentWorkflowType();

        if ($workflowType === null) {
            return $this->promptToStartWorkflow($guide, $output);
        }

        // Get current step
        $currentStep = $guide->currentStep();

        if ($currentStep === null) {
            return $this->handleWorkflowComplete($guide, $output);
        }

        // Handle skip
        if ($input->getOption('skip')) {
            return $this->skipStep($guide, $currentStep, $output);
        }

        // Show current step and prompt for action
        return $this->handleCurrentStep($guide, $currentStep, $input, $output);
    }

    private function startNewWorkflow(WorkflowGuide $guide, string $typeString, OutputInterface $output): int
    {
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
        $output->writeln('');

        // Show first step
        $firstStep = $guide->currentStep();
        if ($firstStep) {
            note("First step: {$firstStep->name}");
            $output->writeln('Run <info>laraforge next</info> to continue.');
        }

        return self::SUCCESS;
    }

    private function endWorkflow(WorkflowGuide $guide, OutputInterface $output): int
    {
        $type = $guide->currentWorkflowType();

        if ($type === null) {
            warning('No active workflow to end.');

            return self::FAILURE;
        }

        $name = $guide->workflowName() ?? $type->label();

        if (confirm("End workflow '{$name}'?", true)) {
            $guide->endWorkflow();
            info("Workflow '{$name}' completed!");
            $output->writeln('');
            note('Run `laraforge next` to start a new workflow.');
        }

        return self::SUCCESS;
    }

    private function promptToStartWorkflow(WorkflowGuide $guide, OutputInterface $output): int
    {
        $output->writeln('');

        if (! $guide->isProjectInitialized()) {
            // First time - start onboarding
            info('Welcome to LaraForge!');
            $output->writeln('Let\'s set up your project.');
            $output->writeln('');

            $guide->startWorkflow(WorkflowType::ONBOARDING, 'Project Setup');

            $firstStep = $guide->currentStep();
            if ($firstStep) {
                return $this->handleCurrentStep($guide, $firstStep, new \Symfony\Component\Console\Input\ArrayInput([]), $output);
            }

            return self::SUCCESS;
        }

        // Project exists, ask what they want to do
        info('No active workflow. What would you like to do?');
        $output->writeln('');

        $options = [];
        foreach (WorkflowType::forExistingProject() as $type) {
            $options[$type->value] = "{$type->icon()} {$type->label()} - {$type->description()}";
        }

        $choice = select(
            label: 'Select workflow type',
            options: $options,
        );

        return $this->startNewWorkflow($guide, $choice, $output);
    }

    private function handleWorkflowComplete(WorkflowGuide $guide, OutputInterface $output): int
    {
        $type = $guide->currentWorkflowType();
        $name = $guide->workflowName() ?? $type?->label() ?? 'Workflow';

        $output->writeln('');
        $output->writeln('<fg=green>');
        $output->writeln('  ╔═══════════════════════════════════════════╗');
        $output->writeln('  ║                                           ║');
        $output->writeln('  ║   All steps completed!                    ║');
        $output->writeln('  ║                                           ║');
        $output->writeln('  ╚═══════════════════════════════════════════╝');
        $output->writeln('</>');
        $output->writeln('');

        info("'{$name}' is complete!");

        if (confirm('Start a new workflow?', true)) {
            $guide->endWorkflow();

            return $this->promptToStartWorkflow($guide, $output);
        }

        $guide->endWorkflow();

        return self::SUCCESS;
    }

    private function showStatus(WorkflowGuide $guide, OutputInterface $output): int
    {
        $type = $guide->currentWorkflowType();
        $name = $guide->workflowName();
        $progress = $guide->progressPercentage();
        $completed = $guide->completedSteps();
        $remaining = $guide->remainingSteps();

        $output->writeln('');

        if ($type === null) {
            note('No active workflow.');
            $output->writeln('Run <info>laraforge next</info> to start one.');

            return self::SUCCESS;
        }

        $output->writeln("<comment>{$type->icon()} {$type->label()}</comment>");
        if ($name) {
            $output->writeln("<info>{$name}</info>");
        }
        $output->writeln(str_repeat('─', 50));

        // Progress bar
        $barWidth = 40;
        $filledWidth = (int) round($barWidth * ($progress / 100));
        $emptyWidth = $barWidth - $filledWidth;
        $progressBar = str_repeat('█', $filledWidth).str_repeat('░', $emptyWidth);
        $output->writeln("Progress: [{$progressBar}] {$progress}%");
        $output->writeln('');

        // Completed steps
        if (count($completed) > 0) {
            $output->writeln('<comment>Completed:</comment>');
            foreach ($completed as $step) {
                $output->writeln("  <info>✓</info> {$step->name}");
            }
            $output->writeln('');
        }

        // Remaining steps
        if (count($remaining) > 0) {
            $output->writeln('<comment>Remaining:</comment>');
            foreach ($remaining as $step) {
                $marker = $step->required ? '<fg=red>*</>' : '<fg=gray>○</>';
                $output->writeln("  {$marker} {$step->name}");
            }
            $output->writeln('');
            $output->writeln('<fg=gray>* = required, ○ = optional</>');
        }

        return self::SUCCESS;
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

    private function skipStep(WorkflowGuide $guide, GuideStep $step, OutputInterface $output): int
    {
        if ($step->required) {
            warning("Cannot skip '{$step->name}' - this step is required.");

            return self::FAILURE;
        }

        $guide->markSkipped($step->id);
        info("Skipped: {$step->name}");

        $next = $guide->currentStep();
        if ($next) {
            $output->writeln('');
            note("Next up: {$next->name}");
            $output->writeln('Run <info>laraforge next</info> to continue.');
        }

        return self::SUCCESS;
    }

    private function handleCurrentStep(
        WorkflowGuide $guide,
        GuideStep $step,
        InputInterface $input,
        OutputInterface $output
    ): int {
        $type = $guide->currentWorkflowType();
        $output->writeln('');

        // Show workflow context
        if ($type) {
            $name = $guide->workflowName();
            $progress = $guide->progressPercentage();
            $output->writeln("<fg=cyan>{$type->icon()} {$type->label()}</> {$name} <fg=gray>[{$progress}%]</>");
            $output->writeln('');
        }

        // Show step header
        $marker = $step->required ? '<fg=red>[Required]</>' : '<fg=yellow>[Optional]</>';
        $phase = '<fg=cyan>'.ucfirst($step->phase).'</>';
        $output->writeln("{$marker} {$phase}");
        $output->writeln("<comment>{$step->name}</comment>");
        $output->writeln(str_repeat('─', 50));
        $output->writeln('');
        $output->writeln($step->description);
        $output->writeln('');

        // Handle manual steps
        if ($step->manualStep) {
            return $this->handleManualStep($guide, $step, $output);
        }

        // Show command
        if ($step->command) {
            $output->writeln('<comment>Command:</comment>');
            $output->writeln("  <info>{$step->command}</info>");

            if ($step->hasAlternative()) {
                $output->writeln('');
                $output->writeln('<comment>Alternative:</comment>');
                $output->writeln("  <info>{$step->alternativeCommand}</info>");
            }
            $output->writeln('');
        }

        // Auto mode
        if ($input->getOption('auto') && $step->hasCommand()) {
            return $this->executeStep($guide, $step, $output);
        }

        return $this->promptForAction($guide, $step, $output);
    }

    private function promptForAction(WorkflowGuide $guide, GuideStep $step, OutputInterface $output): int
    {
        $options = [];

        if ($step->hasCommand()) {
            $options['run'] = 'Run this command';
        }

        if ($step->hasAlternative()) {
            $options['alternative'] = 'Run alternative command';
        }

        if ($step->canSkip()) {
            $options['skip'] = 'Skip this step';
        }

        $options['later'] = 'Do this later (exit)';
        $options['done'] = 'I already did this (mark complete)';

        $action = select(
            label: 'What would you like to do?',
            options: $options,
            default: $step->hasCommand() ? 'run' : 'done',
        );

        return match ($action) {
            'run' => $this->executeStep($guide, $step, $output),
            'alternative' => $this->executeStep($guide, $step, $output, useAlternative: true),
            'skip' => $this->skipStep($guide, $step, $output),
            'done' => $this->markDone($guide, $step, $output),
            'later' => self::SUCCESS,
            default => self::SUCCESS,
        };
    }

    private function executeStep(
        WorkflowGuide $guide,
        GuideStep $step,
        OutputInterface $output,
        bool $useAlternative = false
    ): int {
        $command = $useAlternative ? $step->alternativeCommand : $step->command;

        if (! $command) {
            warning('No command to execute.');

            return self::FAILURE;
        }

        $output->writeln('');
        $output->writeln("<comment>Running:</comment> <info>{$command}</info>");
        $output->writeln('');

        $process = Process::fromShellCommandline($command, $this->laraforge->workingDirectory());
        $process->setTimeout(300);
        $process->setTty(Process::isTtySupported());

        $exitCode = $process->run(function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });

        $output->writeln('');

        if ($exitCode === 0) {
            $guide->markCompleted($step->id);
            info("✓ {$step->name} completed!");
            $this->showNextStepHint($guide, $output);

            return self::SUCCESS;
        }

        warning("Command exited with code {$exitCode}");

        if (confirm('Mark this step as complete anyway?', false)) {
            $guide->markCompleted($step->id);
            $this->showNextStepHint($guide, $output);
        }

        return $exitCode;
    }

    private function handleManualStep(WorkflowGuide $guide, GuideStep $step, OutputInterface $output): int
    {
        note('This is a manual step. Complete it in your editor/IDE.');
        $output->writeln('');

        $options = [
            'done' => 'I have completed this step',
            'later' => 'I\'ll do this later',
        ];

        if ($step->canSkip()) {
            $options['skip'] = 'Skip this step';
        }

        $action = select(
            label: 'What is the status?',
            options: $options,
        );

        return match ($action) {
            'done' => $this->markDone($guide, $step, $output),
            'skip' => $this->skipStep($guide, $step, $output),
            'later' => self::SUCCESS,
            default => self::SUCCESS,
        };
    }

    private function markDone(WorkflowGuide $guide, GuideStep $step, OutputInterface $output): int
    {
        $guide->markCompleted($step->id);
        info("✓ {$step->name} marked as complete!");
        $this->showNextStepHint($guide, $output);

        return self::SUCCESS;
    }

    private function showNextStepHint(WorkflowGuide $guide, OutputInterface $output): void
    {
        $next = $guide->currentStep();

        if ($next) {
            $output->writeln('');
            $stepType = $next->required ? 'Required' : 'Optional';
            note("Next: {$next->name} ({$stepType})");
            $output->writeln('Run <info>laraforge next</info> to continue.');
        }
    }

    private function handleSessionConflict(
        SessionManager $sessionManager,
        \LaraForge\Session\SessionConflict $conflict,
        OutputInterface $output
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

        $process = Process::fromShellCommandline(
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
