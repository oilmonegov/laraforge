<?php

declare(strict_types=1);

namespace LaraForge\Commands\Concerns;

use LaraForge\Guide\GuideStep;
use LaraForge\Guide\WorkflowGuide;
use LaraForge\Guide\WorkflowType;
use LaraForge\Session\SessionManager;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

trait SuggestsNextStep
{
    /**
     * Mark a step as completed and prompt for next action.
     */
    protected function completeStepAndSuggestNext(
        string $stepId,
        OutputInterface $output,
        ?string $workingDirectory = null,
    ): void {
        $workingDir = $workingDirectory ?? $this->laraforge->workingDirectory();
        $guide = new WorkflowGuide($workingDir);

        // Mark this step as completed
        $guide->markCompleted($stepId);

        // Update session heartbeat
        $sessionManager = new SessionManager($workingDir);
        $sessionManager->heartbeat();

        // Prompt for next action
        $this->promptNextAction($guide, $output, $workingDir);
    }

    /**
     * Show what the next step should be and prompt for action.
     */
    protected function showNextStepSuggestion(
        WorkflowGuide $guide,
        OutputInterface $output,
        ?string $workingDirectory = null,
    ): void {
        $workingDir = $workingDirectory ?? $this->laraforge->workingDirectory();

        // Update session heartbeat
        $sessionManager = new SessionManager($workingDir);
        $sessionManager->heartbeat();

        $this->promptNextAction($guide, $output, $workingDir);
    }

    /**
     * Prompt the user for the next action with selectable options.
     */
    private function promptNextAction(
        WorkflowGuide $guide,
        OutputInterface $output,
        string $workingDir,
    ): void {
        $nextStep = $guide->currentStep();

        if ($nextStep === null) {
            $this->promptWorkflowComplete($guide, $output, $workingDir);

            return;
        }

        $this->promptStepAction($nextStep, $guide, $output, $workingDir);
    }

    /**
     * Prompt for action on a specific step.
     */
    private function promptStepAction(
        GuideStep $step,
        WorkflowGuide $guide,
        OutputInterface $output,
        string $workingDir,
    ): void {
        $output->writeln('');

        // Show progress context
        $progress = $guide->progressPercentage();
        $type = $guide->currentWorkflowType();
        if ($type !== null) {
            $name = $guide->workflowName();
            $output->writeln("<fg=cyan>{$type->icon()} {$type->label()}</> {$name} <fg=gray>[{$progress}%]</>");
        }

        $output->writeln('');
        $output->writeln('┌─────────────────────────────────────────────────────┐');
        $output->writeln('│  <fg=cyan>What\'s Next?</>                                        │');
        $output->writeln('└─────────────────────────────────────────────────────┘');
        $output->writeln('');

        // Build options
        $options = $this->buildStepOptions($step, $guide);

        $choice = select(
            label: $this->formatStepLabel($step),
            options: $options,
            default: array_key_first($options),
        );

        $this->executeChoice($choice, $step, $guide, $output, $workingDir);
    }

    /**
     * Build the options array for a step.
     *
     * @return array<string, string>
     */
    private function buildStepOptions(GuideStep $step, WorkflowGuide $guide): array
    {
        $options = [];

        // Primary action - run the command
        if ($step->command) {
            $options['run'] = "Run: {$step->command}";
        }

        // Alternative command if available
        if ($step->hasAlternative()) {
            $options['alternative'] = "Alternative: {$step->alternativeCommand}";
        }

        // For manual steps
        if ($step->manualStep) {
            $options['done'] = 'I\'ve completed this step';
        }

        // Skip option for optional steps
        if (! $step->required) {
            $options['skip'] = 'Skip this step';
        }

        // Show other pending steps
        $remaining = $guide->remainingSteps();
        $otherSteps = array_filter($remaining, fn ($s) => $s->id !== $step->id);
        if (count($otherSteps) > 0 && count($otherSteps) <= 3) {
            foreach (array_slice($otherSteps, 0, 2) as $other) {
                if ($other->command) {
                    $options["jump:{$other->id}"] = "Jump to: {$other->name}";
                }
            }
        }

        // Always add these options
        $options['custom'] = 'Run a custom command...';
        $options['status'] = 'Show full status';
        $options['exit'] = 'Exit (continue later)';

        return $options;
    }

    /**
     * Format the step as a label for the select prompt.
     */
    private function formatStepLabel(GuideStep $step): string
    {
        $marker = $step->required ? '[Required]' : '[Optional]';

        return "{$marker} {$step->name} - {$step->description}";
    }

    /**
     * Execute the user's choice.
     */
    private function executeChoice(
        string $choice,
        GuideStep $step,
        WorkflowGuide $guide,
        OutputInterface $output,
        string $workingDir,
    ): void {
        switch ($choice) {
            case 'run':
                $this->runCommand($step->command, $step, $guide, $output, $workingDir);
                break;

            case 'alternative':
                $this->runCommand($step->alternativeCommand, $step, $guide, $output, $workingDir);
                break;

            case 'done':
                $guide->markCompleted($step->id);
                $output->writeln('');
                $output->writeln("<info>✓ {$step->name} marked as complete!</info>");
                $this->promptNextAction($guide, $output, $workingDir);
                break;

            case 'skip':
                $guide->markSkipped($step->id);
                $output->writeln('');
                $output->writeln("<fg=yellow>⊘ Skipped: {$step->name}</>");
                $this->promptNextAction($guide, $output, $workingDir);
                break;

            case 'custom':
                $this->runCustomCommand($guide, $output, $workingDir);
                break;

            case 'status':
                $this->showFullStatus($guide, $output);
                $this->promptNextAction($guide, $output, $workingDir);
                break;

            case 'exit':
                $output->writeln('');
                $output->writeln('<fg=gray>Run `laraforge next` when you\'re ready to continue.</>');
                break;

            default:
                // Handle jump to another step
                if (str_starts_with($choice, 'jump:')) {
                    $jumpStepId = substr($choice, 5);
                    $jumpStep = $guide->getStep($jumpStepId);
                    if ($jumpStep && $jumpStep->command) {
                        $this->runCommand($jumpStep->command, $jumpStep, $guide, $output, $workingDir);
                    }
                }
                break;
        }
    }

    /**
     * Run a command and handle the result.
     */
    private function runCommand(
        ?string $command,
        GuideStep $step,
        WorkflowGuide $guide,
        OutputInterface $output,
        string $workingDir,
    ): void {
        if (! $command) {
            return;
        }

        $output->writeln('');
        $output->writeln("<comment>Running:</comment> <info>{$command}</info>");
        $output->writeln('');

        // Check if it's a laraforge command
        if (str_starts_with($command, 'laraforge ')) {
            // For laraforge commands, mark step complete and let the command handle the next suggestion
            $guide->markCompleted($step->id);

            $process = Process::fromShellCommandline($command, $workingDir);
            $process->setTimeout(300);
            $process->setTty(Process::isTtySupported());
            $process->run(function ($type, $buffer) use ($output): void {
                $output->write($buffer);
            });

            // The called command will handle its own "what's next"
            return;
        }

        // For non-laraforge commands, run and then prompt for next
        $process = Process::fromShellCommandline($command, $workingDir);
        $process->setTimeout(300);
        $process->setTty(Process::isTtySupported());

        $exitCode = $process->run(function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });

        $output->writeln('');

        if ($exitCode === 0) {
            $guide->markCompleted($step->id);
            $output->writeln("<info>✓ {$step->name} completed!</info>");
        } else {
            $output->writeln("<fg=yellow>⚠ Command exited with code {$exitCode}</>");

            $markComplete = select(
                label: 'What would you like to do?',
                options: [
                    'retry' => 'Retry the command',
                    'complete' => 'Mark as complete anyway',
                    'skip' => 'Skip this step',
                    'exit' => 'Exit and fix manually',
                ],
                default: 'retry',
            );

            match ($markComplete) {
                'retry' => $this->runCommand($command, $step, $guide, $output, $workingDir),
                'complete' => $guide->markCompleted($step->id),
                'skip' => $guide->markSkipped($step->id),
                'exit' => null,
            };

            if ($markComplete === 'exit') {
                return;
            }
        }

        // Continue to next step
        $this->promptNextAction($guide, $output, $workingDir);
    }

    /**
     * Run a custom command entered by the user.
     */
    private function runCustomCommand(
        WorkflowGuide $guide,
        OutputInterface $output,
        string $workingDir,
    ): void {
        $command = text(
            label: 'Enter command to run',
            placeholder: 'composer test',
            required: true,
        );

        $output->writeln('');
        $output->writeln("<comment>Running:</comment> <info>{$command}</info>");
        $output->writeln('');

        $process = Process::fromShellCommandline($command, $workingDir);
        $process->setTimeout(300);
        $process->setTty(Process::isTtySupported());

        $process->run(function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });

        $output->writeln('');

        // After custom command, prompt for next action again
        $this->promptNextAction($guide, $output, $workingDir);
    }

    /**
     * Show the full workflow status.
     */
    private function showFullStatus(WorkflowGuide $guide, OutputInterface $output): void
    {
        $type = $guide->currentWorkflowType();
        $name = $guide->workflowName();
        $progress = $guide->progressPercentage();
        $completed = $guide->completedSteps();
        $remaining = $guide->remainingSteps();

        $output->writeln('');
        $output->writeln('┌─────────────────────────────────────────────────────┐');
        $output->writeln('│  <fg=cyan>Workflow Status</>                                     │');
        $output->writeln('└─────────────────────────────────────────────────────┘');
        $output->writeln('');

        if ($type !== null) {
            $output->writeln("<comment>{$type->icon()} {$type->label()}</comment>: {$name}");
        }

        // Progress bar
        $barWidth = 40;
        $filledWidth = (int) round($barWidth * ($progress / 100));
        $emptyWidth = $barWidth - $filledWidth;
        $progressBar = str_repeat('█', $filledWidth).str_repeat('░', $emptyWidth);
        $output->writeln("Progress: [{$progressBar}] {$progress}%");
        $output->writeln('');

        // Completed steps
        if (count($completed) > 0) {
            $output->writeln('<fg=green>Completed:</>');
            foreach ($completed as $step) {
                $output->writeln("  <info>✓</info> {$step->name}");
            }
            $output->writeln('');
        }

        // Remaining steps
        if (count($remaining) > 0) {
            $output->writeln('<fg=yellow>Remaining:</>');
            foreach ($remaining as $step) {
                $marker = $step->required ? '<fg=red>*</>' : '<fg=gray>○</>';
                $output->writeln("  {$marker} {$step->name}");
            }
            $output->writeln('');
        }
    }

    /**
     * Prompt when workflow is complete.
     */
    private function promptWorkflowComplete(
        WorkflowGuide $guide,
        OutputInterface $output,
        string $workingDir,
    ): void {
        $type = $guide->currentWorkflowType();
        $name = $guide->workflowName();

        $output->writeln('');
        $output->writeln('<fg=green>┌─────────────────────────────────────────────────────┐</>');
        $output->writeln('<fg=green>│  ✓ All steps completed!                             │</>');
        $output->writeln('<fg=green>└─────────────────────────────────────────────────────┘</>');
        $output->writeln('');

        if ($name) {
            $output->writeln("<info>\"{$name}\" is complete!</info>");
            $output->writeln('');
        }

        // Build options for what to do next
        $options = [];

        foreach (WorkflowType::forExistingProject() as $workflowType) {
            $options["start:{$workflowType->value}"] = "{$workflowType->icon()} Start new {$workflowType->label()}";
        }

        $options['custom'] = 'Run a custom command...';
        $options['exit'] = 'Exit';

        $choice = select(
            label: 'What would you like to do next?',
            options: $options,
            default: 'exit',
        );

        if ($choice === 'exit') {
            $guide->endWorkflow();

            return;
        }

        if ($choice === 'custom') {
            $guide->endWorkflow();
            $this->runCustomCommand($guide, $output, $workingDir);

            return;
        }

        if (str_starts_with($choice, 'start:')) {
            $guide->endWorkflow();
            $workflowTypeValue = substr($choice, 6);
            $workflowType = WorkflowType::tryFrom($workflowTypeValue);

            if ($workflowType) {
                $workflowName = text(
                    label: "What's the name of this {$workflowType->label()}?",
                    placeholder: match ($workflowType) {
                        WorkflowType::FEATURE => 'User Authentication',
                        WorkflowType::BUGFIX => 'Fix login redirect issue',
                        WorkflowType::REFACTOR => 'Refactor user service',
                        WorkflowType::HOTFIX => 'Fix production crash',
                        WorkflowType::ONBOARDING => 'Project Setup',
                    },
                    required: true,
                );

                $guide->startWorkflow($workflowType, $workflowName);
                $output->writeln('');
                $output->writeln("<info>{$workflowType->icon()} Started: {$workflowName}</info>");

                $this->promptNextAction($guide, $output, $workingDir);
            }
        }
    }
}
