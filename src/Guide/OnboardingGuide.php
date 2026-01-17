<?php

declare(strict_types=1);

namespace LaraForge\Guide;

use LaraForge\Project\ProjectState;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class OnboardingGuide
{
    private Filesystem $filesystem;

    private string $progressPath;

    /**
     * @var array<string, mixed>
     */
    private array $progress = [];

    /**
     * @param  ProjectState|null  $state  Reserved for future use (feature-specific steps)
     */
    public function __construct(
        private readonly string $workingDirectory,
        ?ProjectState $state = null,
    ) {
        // $state will be used for feature-specific workflow steps in a future update
        unset($state);
        $this->filesystem = new Filesystem;
        $this->progressPath = $workingDirectory.'/.laraforge/guide-progress.yaml';
        $this->loadProgress();
    }

    /**
     * Get all defined steps in the onboarding workflow.
     *
     * @return array<GuideStep>
     */
    public function allSteps(): array
    {
        return [
            // Phase 1: Project Setup
            new GuideStep(
                id: 'init',
                name: 'Initialize LaraForge',
                description: 'Set up LaraForge in your project. This creates the .laraforge directory and configuration files.',
                command: 'laraforge init',
                phase: 'setup',
                required: true,
                order: 1,
            ),

            // Phase 2: Requirements
            new GuideStep(
                id: 'import-prd',
                name: 'Import or Create PRD',
                description: 'Import an existing Product Requirements Document or create a new one. The PRD defines what you\'re building and why.',
                command: 'laraforge prd:import <file> --create-feature',
                alternativeCommand: 'laraforge skill:run create-prd --params=title="Your Feature"',
                phase: 'requirements',
                required: true,
                order: 2,
                prerequisite: 'init',
            ),

            new GuideStep(
                id: 'create-frd',
                name: 'Create Feature Requirements Document',
                description: 'Generate a detailed FRD from your PRD. This breaks down the PRD into technical specifications and implementation details.',
                command: 'laraforge skill:run create-frd',
                phase: 'requirements',
                required: true,
                order: 3,
                prerequisite: 'import-prd',
            ),

            new GuideStep(
                id: 'create-test-contract',
                name: 'Define Test Contract',
                description: 'Create acceptance criteria and test contracts. This defines how you\'ll know the feature is complete.',
                command: 'laraforge skill:run create-test-contract',
                phase: 'requirements',
                required: false,
                order: 4,
                prerequisite: 'create-frd',
            ),

            // Phase 3: Development Setup
            new GuideStep(
                id: 'create-branch',
                name: 'Create Feature Branch',
                description: 'Create a Git branch for your feature. This isolates your work from the main codebase.',
                command: 'laraforge skill:run branch',
                phase: 'development',
                required: true,
                order: 5,
                prerequisite: 'create-frd',
            ),

            new GuideStep(
                id: 'install-hooks',
                name: 'Install Git Hooks',
                description: 'Set up Git hooks for code quality checks. This ensures code is linted and tested before commits.',
                command: 'laraforge hooks:install',
                phase: 'development',
                required: false,
                order: 6,
                prerequisite: 'init',
            ),

            // Phase 4: Implementation
            new GuideStep(
                id: 'generate-tests',
                name: 'Generate Feature Tests',
                description: 'Create test files for your feature. Start with tests to guide your implementation (TDD approach).',
                command: 'laraforge generate feature-test',
                phase: 'implementation',
                required: false,
                order: 7,
                prerequisite: 'create-branch',
            ),

            new GuideStep(
                id: 'implement',
                name: 'Implement Feature',
                description: 'Write the code for your feature. Use the FRD and test contracts as your guide.',
                command: null, // No command - this is manual work
                phase: 'implementation',
                required: true,
                order: 8,
                prerequisite: 'create-branch',
                manualStep: true,
            ),

            // Phase 5: Verification
            new GuideStep(
                id: 'run-tests',
                name: 'Run Tests',
                description: 'Execute your test suite to verify the implementation works correctly.',
                command: './vendor/bin/pest',
                phase: 'verification',
                required: true,
                order: 9,
                prerequisite: 'implement',
            ),

            new GuideStep(
                id: 'run-lint',
                name: 'Run Code Style Checks',
                description: 'Check and fix code style issues to maintain consistency.',
                command: 'composer lint:fix',
                phase: 'verification',
                required: false,
                order: 10,
                prerequisite: 'implement',
            ),

            new GuideStep(
                id: 'run-analysis',
                name: 'Run Static Analysis',
                description: 'Analyze code for potential bugs and type errors.',
                command: 'composer analyse',
                phase: 'verification',
                required: false,
                order: 11,
                prerequisite: 'implement',
            ),

            // Phase 6: Review & Merge
            new GuideStep(
                id: 'create-pr',
                name: 'Create Pull Request',
                description: 'Open a pull request for code review.',
                command: 'gh pr create',
                phase: 'review',
                required: true,
                order: 12,
                prerequisite: 'run-tests',
            ),

            new GuideStep(
                id: 'merge',
                name: 'Merge Feature',
                description: 'After approval, merge your feature into the main branch.',
                command: 'gh pr merge',
                phase: 'review',
                required: true,
                order: 13,
                prerequisite: 'create-pr',
            ),
        ];
    }

    /**
     * Get the current step the user should work on.
     */
    public function currentStep(): ?GuideStep
    {
        $steps = $this->allSteps();

        foreach ($steps as $step) {
            if ($this->isStepCompleted($step->id)) {
                continue;
            }

            if ($this->isStepSkipped($step->id)) {
                continue;
            }

            // Check if prerequisite is met
            if ($step->prerequisite !== null && ! $this->isStepCompleted($step->prerequisite)) {
                continue;
            }

            // Check if step should be auto-detected as complete
            if ($this->detectStepCompletion($step)) {
                $this->markCompleted($step->id);

                continue;
            }

            return $step;
        }

        return null;
    }

    /**
     * Get the next step after the current one.
     */
    public function nextStep(): ?GuideStep
    {
        $current = $this->currentStep();
        if ($current === null) {
            return null;
        }

        $steps = $this->allSteps();
        $foundCurrent = false;

        foreach ($steps as $step) {
            if ($step->id === $current->id) {
                $foundCurrent = true;

                continue;
            }

            if ($foundCurrent && ! $this->isStepCompleted($step->id) && ! $this->isStepSkipped($step->id)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Get all remaining steps.
     *
     * @return array<GuideStep>
     */
    public function remainingSteps(): array
    {
        $remaining = [];
        $steps = $this->allSteps();

        foreach ($steps as $step) {
            if (! $this->isStepCompleted($step->id) && ! $this->isStepSkipped($step->id)) {
                $remaining[] = $step;
            }
        }

        return $remaining;
    }

    /**
     * Get all completed steps.
     *
     * @return array<GuideStep>
     */
    public function completedSteps(): array
    {
        $completed = [];
        $steps = $this->allSteps();

        foreach ($steps as $step) {
            if ($this->isStepCompleted($step->id)) {
                $completed[] = $step;
            }
        }

        return $completed;
    }

    /**
     * Mark a step as completed.
     */
    public function markCompleted(string $stepId): void
    {
        $this->progress['completed'][$stepId] = [
            'at' => date('c'),
        ];
        $this->saveProgress();
    }

    /**
     * Mark a step as skipped.
     */
    public function markSkipped(string $stepId): void
    {
        $this->progress['skipped'][$stepId] = [
            'at' => date('c'),
        ];
        $this->saveProgress();
    }

    /**
     * Check if a step is completed.
     */
    public function isStepCompleted(string $stepId): bool
    {
        return isset($this->progress['completed'][$stepId]);
    }

    /**
     * Check if a step is skipped.
     */
    public function isStepSkipped(string $stepId): bool
    {
        return isset($this->progress['skipped'][$stepId]);
    }

    /**
     * Get overall progress percentage.
     */
    public function progressPercentage(): int
    {
        $steps = $this->allSteps();
        $total = count($steps);
        $completed = count($this->progress['completed'] ?? []);
        $skipped = count($this->progress['skipped'] ?? []);

        if ($total === 0) {
            return 100;
        }

        return (int) round((($completed + $skipped) / $total) * 100);
    }

    /**
     * Get current phase name.
     */
    public function currentPhase(): string
    {
        $current = $this->currentStep();

        if ($current === null) {
            return 'complete';
        }

        return $current->phase;
    }

    /**
     * Reset all progress.
     */
    public function reset(): void
    {
        $this->progress = [
            'completed' => [],
            'skipped' => [],
            'started_at' => date('c'),
        ];
        $this->saveProgress();
    }

    /**
     * Get step by ID.
     */
    public function getStep(string $id): ?GuideStep
    {
        foreach ($this->allSteps() as $step) {
            if ($step->id === $id) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Auto-detect if a step has been completed based on project state.
     */
    private function detectStepCompletion(GuideStep $step): bool
    {
        return match ($step->id) {
            'init' => $this->filesystem->exists($this->workingDirectory.'/.laraforge/project.yaml'),
            'import-prd' => $this->hasPrdDocument(),
            'create-frd' => $this->hasFrdDocument(),
            'create-branch' => $this->isOnFeatureBranch(),
            'install-hooks' => $this->hasGitHooks(),
            default => false,
        };
    }

    private function hasPrdDocument(): bool
    {
        $prdDir = $this->workingDirectory.'/.laraforge/docs/prd';

        if (! $this->filesystem->exists($prdDir)) {
            return false;
        }

        $files = glob($prdDir.'/*.{yaml,yml,md}', GLOB_BRACE);

        return ! empty($files);
    }

    private function hasFrdDocument(): bool
    {
        $frdDir = $this->workingDirectory.'/.laraforge/docs/frd';

        if (! $this->filesystem->exists($frdDir)) {
            return false;
        }

        $files = glob($frdDir.'/*.{yaml,yml,md}', GLOB_BRACE);

        return ! empty($files);
    }

    private function isOnFeatureBranch(): bool
    {
        $gitHead = $this->workingDirectory.'/.git/HEAD';

        if (! $this->filesystem->exists($gitHead)) {
            return false;
        }

        $head = file_get_contents($gitHead);
        if ($head === false) {
            return false;
        }

        // Check if on a feature branch (not main/master/develop)
        $mainBranches = ['main', 'master', 'develop', 'development'];
        foreach ($mainBranches as $branch) {
            if (str_contains($head, "refs/heads/{$branch}")) {
                return false;
            }
        }

        return str_contains($head, 'refs/heads/');
    }

    private function hasGitHooks(): bool
    {
        $hooksDir = $this->workingDirectory.'/.git/hooks';

        return $this->filesystem->exists($hooksDir.'/pre-commit');
    }

    private function loadProgress(): void
    {
        if ($this->filesystem->exists($this->progressPath)) {
            $data = Yaml::parseFile($this->progressPath);
            $this->progress = is_array($data) ? $data : [];
        } else {
            $this->progress = [
                'completed' => [],
                'skipped' => [],
                'started_at' => date('c'),
            ];
        }
    }

    private function saveProgress(): void
    {
        $dir = dirname($this->progressPath);
        if (! $this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir, 0755);
        }

        $yaml = Yaml::dump($this->progress, 4);
        $this->filesystem->dumpFile($this->progressPath, $yaml);
    }
}
