<?php

declare(strict_types=1);

namespace LaraForge\Guide;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class WorkflowGuide
{
    private Filesystem $filesystem;

    private string $progressPath;

    /**
     * @var array<string, mixed>
     */
    private array $progress = [];

    public function __construct(
        private readonly string $workingDirectory,
    ) {
        $this->filesystem = new Filesystem;
        $this->progressPath = $workingDirectory.'/.laraforge/workflow-progress.yaml';
        $this->loadProgress();
    }

    /**
     * Check if project is initialized.
     */
    public function isProjectInitialized(): bool
    {
        return $this->filesystem->exists($this->workingDirectory.'/.laraforge/project.yaml');
    }

    /**
     * Get current active workflow type.
     */
    public function currentWorkflowType(): ?WorkflowType
    {
        $type = $this->progress['current_workflow'] ?? null;

        if ($type === null) {
            // Auto-detect: if not initialized, start with onboarding
            if (! $this->isProjectInitialized()) {
                return WorkflowType::ONBOARDING;
            }

            return null;
        }

        return WorkflowType::tryFrom($type);
    }

    /**
     * Start a new workflow.
     */
    public function startWorkflow(WorkflowType $type, ?string $name = null): void
    {
        $this->progress['current_workflow'] = $type->value;
        $this->progress['workflow_name'] = $name;
        $this->progress['workflow_started_at'] = date('c');
        $this->progress['completed'] = [];
        $this->progress['skipped'] = [];
        $this->saveProgress();
    }

    /**
     * End the current workflow.
     */
    public function endWorkflow(): void
    {
        // Archive the completed workflow
        $this->progress['history'][] = [
            'type' => $this->progress['current_workflow'] ?? null,
            'name' => $this->progress['workflow_name'] ?? null,
            'started_at' => $this->progress['workflow_started_at'] ?? null,
            'completed_at' => date('c'),
            'steps_completed' => count($this->progress['completed'] ?? []),
        ];

        $this->progress['current_workflow'] = null;
        $this->progress['workflow_name'] = null;
        $this->progress['workflow_started_at'] = null;
        $this->progress['completed'] = [];
        $this->progress['skipped'] = [];
        $this->saveProgress();
    }

    /**
     * Get workflow name (e.g., feature name, bug description).
     */
    public function workflowName(): ?string
    {
        return $this->progress['workflow_name'] ?? null;
    }

    /**
     * Get all steps for the current workflow.
     *
     * @return array<GuideStep>
     */
    public function allSteps(): array
    {
        $type = $this->currentWorkflowType();

        if ($type === null) {
            return [];
        }

        return match ($type) {
            WorkflowType::ONBOARDING => $this->onboardingSteps(),
            WorkflowType::FEATURE => $this->featureSteps(),
            WorkflowType::BUGFIX => $this->bugfixSteps(),
            WorkflowType::REFACTOR => $this->refactorSteps(),
            WorkflowType::HOTFIX => $this->hotfixSteps(),
        };
    }

    /**
     * Get the current step.
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

            if ($step->prerequisite !== null && ! $this->isStepCompleted($step->prerequisite)) {
                continue;
            }

            if ($this->detectStepCompletion($step)) {
                $this->markCompleted($step->id);

                continue;
            }

            return $step;
        }

        return null;
    }

    /**
     * Mark a step as completed.
     */
    public function markCompleted(string $stepId): void
    {
        $this->progress['completed'][$stepId] = ['at' => date('c')];
        $this->saveProgress();
    }

    /**
     * Mark a step as skipped.
     */
    public function markSkipped(string $stepId): void
    {
        $this->progress['skipped'][$stepId] = ['at' => date('c')];
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
     * Get progress percentage.
     */
    public function progressPercentage(): int
    {
        $steps = $this->allSteps();
        $total = count($steps);

        if ($total === 0) {
            return 100;
        }

        $completed = count($this->progress['completed'] ?? []);
        $skipped = count($this->progress['skipped'] ?? []);

        return (int) round((($completed + $skipped) / $total) * 100);
    }

    /**
     * Get remaining steps.
     *
     * @return array<GuideStep>
     */
    public function remainingSteps(): array
    {
        $remaining = [];

        foreach ($this->allSteps() as $step) {
            if (! $this->isStepCompleted($step->id) && ! $this->isStepSkipped($step->id)) {
                $remaining[] = $step;
            }
        }

        return $remaining;
    }

    /**
     * Get completed steps.
     *
     * @return array<GuideStep>
     */
    public function completedSteps(): array
    {
        $completed = [];

        foreach ($this->allSteps() as $step) {
            if ($this->isStepCompleted($step->id)) {
                $completed[] = $step;
            }
        }

        return $completed;
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
     * Get workflow history.
     *
     * @return array<array{type: string, name: ?string, started_at: string, completed_at: string, steps_completed: int}>
     */
    public function history(): array
    {
        return $this->progress['history'] ?? [];
    }

    // ========================================
    // WORKFLOW STEP DEFINITIONS
    // ========================================

    /**
     * @return array<GuideStep>
     */
    private function onboardingSteps(): array
    {
        return [
            new GuideStep(
                id: 'init',
                name: 'Initialize LaraForge',
                description: 'Set up LaraForge in your project. This creates the .laraforge directory and configuration files.',
                command: 'laraforge init',
                phase: 'setup',
                required: true,
                order: 1,
            ),
            new GuideStep(
                id: 'install-hooks',
                name: 'Install Git Hooks',
                description: 'Set up Git hooks for code quality checks (lint, test before commit).',
                command: 'laraforge hooks:install',
                phase: 'setup',
                required: false,
                order: 2,
                prerequisite: 'init',
            ),
            new GuideStep(
                id: 'first-feature',
                name: 'Start Your First Feature',
                description: 'You\'re all set up! Start your first feature workflow.',
                command: 'laraforge next --start feature',
                phase: 'complete',
                required: false,
                order: 3,
                prerequisite: 'init',
            ),
        ];
    }

    /**
     * @return array<GuideStep>
     */
    private function featureSteps(): array
    {
        $name = $this->workflowName() ?? 'feature';

        return [
            // Requirements Phase
            new GuideStep(
                id: 'import-prd',
                name: 'Import or Create PRD',
                description: 'Import an existing Product Requirements Document or create a new one.',
                command: 'laraforge prd:import <file> --create-feature',
                alternativeCommand: 'laraforge skill:run create-prd --params=title="'.$name.'"',
                phase: 'requirements',
                required: true,
                order: 1,
            ),
            new GuideStep(
                id: 'create-frd',
                name: 'Create Feature Requirements Document',
                description: 'Generate a detailed FRD from your PRD with technical specifications.',
                command: 'laraforge skill:run create-frd',
                phase: 'requirements',
                required: true,
                order: 2,
                prerequisite: 'import-prd',
            ),
            new GuideStep(
                id: 'create-test-contract',
                name: 'Define Acceptance Criteria',
                description: 'Create test contracts that define when the feature is complete.',
                command: 'laraforge skill:run create-test-contract',
                phase: 'requirements',
                required: false,
                order: 3,
                prerequisite: 'create-frd',
            ),

            // Development Phase
            new GuideStep(
                id: 'create-branch',
                name: 'Create Feature Branch',
                description: 'Create a Git branch for your feature.',
                command: 'laraforge skill:run branch --params=type=feature',
                phase: 'development',
                required: true,
                order: 4,
                prerequisite: 'create-frd',
            ),
            new GuideStep(
                id: 'generate-tests',
                name: 'Generate Feature Tests',
                description: 'Create test files for your feature (TDD approach).',
                command: 'laraforge generate feature-test',
                phase: 'development',
                required: false,
                order: 5,
                prerequisite: 'create-branch',
            ),

            // Implementation Phase
            new GuideStep(
                id: 'implement',
                name: 'Implement Feature',
                description: 'Write the code for your feature using the FRD as your guide.',
                command: null,
                phase: 'implementation',
                required: true,
                order: 6,
                prerequisite: 'create-branch',
                manualStep: true,
            ),

            // Verification Phase
            new GuideStep(
                id: 'run-tests',
                name: 'Run Tests',
                description: 'Execute your test suite to verify the implementation.',
                command: './vendor/bin/pest',
                phase: 'verification',
                required: true,
                order: 7,
                prerequisite: 'implement',
            ),
            new GuideStep(
                id: 'run-quality',
                name: 'Run Quality Checks',
                description: 'Run linting and static analysis.',
                command: 'composer lint:fix && composer analyse',
                phase: 'verification',
                required: false,
                order: 8,
                prerequisite: 'implement',
            ),

            // Review Phase
            new GuideStep(
                id: 'create-pr',
                name: 'Create Pull Request',
                description: 'Open a pull request for code review.',
                command: 'gh pr create --fill',
                phase: 'review',
                required: true,
                order: 9,
                prerequisite: 'run-tests',
            ),
            new GuideStep(
                id: 'merge',
                name: 'Merge Feature',
                description: 'After approval, merge your feature.',
                command: 'gh pr merge --auto --squash',
                phase: 'review',
                required: true,
                order: 10,
                prerequisite: 'create-pr',
            ),
        ];
    }

    /**
     * @return array<GuideStep>
     */
    private function bugfixSteps(): array
    {
        return [
            new GuideStep(
                id: 'create-branch',
                name: 'Create Bugfix Branch',
                description: 'Create a Git branch for your bug fix.',
                command: 'laraforge skill:run branch --params=type=bugfix',
                phase: 'setup',
                required: true,
                order: 1,
            ),
            new GuideStep(
                id: 'write-test',
                name: 'Write Failing Test',
                description: 'Write a test that reproduces the bug (proves it exists).',
                command: null,
                phase: 'development',
                required: false,
                order: 2,
                prerequisite: 'create-branch',
                manualStep: true,
            ),
            new GuideStep(
                id: 'fix-bug',
                name: 'Fix the Bug',
                description: 'Implement the bug fix.',
                command: null,
                phase: 'development',
                required: true,
                order: 3,
                prerequisite: 'create-branch',
                manualStep: true,
            ),
            new GuideStep(
                id: 'run-tests',
                name: 'Run Tests',
                description: 'Verify the fix works and didn\'t break anything.',
                command: './vendor/bin/pest',
                phase: 'verification',
                required: true,
                order: 4,
                prerequisite: 'fix-bug',
            ),
            new GuideStep(
                id: 'create-pr',
                name: 'Create Pull Request',
                description: 'Open a pull request for the bug fix.',
                command: 'gh pr create --fill',
                phase: 'review',
                required: true,
                order: 5,
                prerequisite: 'run-tests',
            ),
            new GuideStep(
                id: 'merge',
                name: 'Merge Fix',
                description: 'Merge the bug fix after approval.',
                command: 'gh pr merge --auto --squash',
                phase: 'review',
                required: true,
                order: 6,
                prerequisite: 'create-pr',
            ),
        ];
    }

    /**
     * @return array<GuideStep>
     */
    private function refactorSteps(): array
    {
        return [
            new GuideStep(
                id: 'create-branch',
                name: 'Create Refactor Branch',
                description: 'Create a Git branch for your refactoring work.',
                command: 'laraforge skill:run branch --params=type=refactor',
                phase: 'setup',
                required: true,
                order: 1,
            ),
            new GuideStep(
                id: 'ensure-tests',
                name: 'Ensure Test Coverage',
                description: 'Make sure you have tests covering the code you\'re refactoring.',
                command: './vendor/bin/pest --coverage',
                phase: 'preparation',
                required: true,
                order: 2,
                prerequisite: 'create-branch',
            ),
            new GuideStep(
                id: 'refactor',
                name: 'Refactor Code',
                description: 'Make your refactoring changes. Keep commits small and focused.',
                command: null,
                phase: 'development',
                required: true,
                order: 3,
                prerequisite: 'ensure-tests',
                manualStep: true,
            ),
            new GuideStep(
                id: 'run-tests',
                name: 'Run Tests',
                description: 'Verify refactoring didn\'t break anything.',
                command: './vendor/bin/pest',
                phase: 'verification',
                required: true,
                order: 4,
                prerequisite: 'refactor',
            ),
            new GuideStep(
                id: 'run-quality',
                name: 'Run Quality Checks',
                description: 'Ensure code quality improved.',
                command: 'composer lint:fix && composer analyse',
                phase: 'verification',
                required: true,
                order: 5,
                prerequisite: 'refactor',
            ),
            new GuideStep(
                id: 'create-pr',
                name: 'Create Pull Request',
                description: 'Open a pull request for the refactoring.',
                command: 'gh pr create --fill',
                phase: 'review',
                required: true,
                order: 6,
                prerequisite: 'run-tests',
            ),
            new GuideStep(
                id: 'merge',
                name: 'Merge Refactor',
                description: 'Merge after approval.',
                command: 'gh pr merge --auto --squash',
                phase: 'review',
                required: true,
                order: 7,
                prerequisite: 'create-pr',
            ),
        ];
    }

    /**
     * @return array<GuideStep>
     */
    private function hotfixSteps(): array
    {
        return [
            new GuideStep(
                id: 'create-branch',
                name: 'Create Hotfix Branch',
                description: 'Create a hotfix branch from main/production.',
                command: 'git checkout main && git pull && git checkout -b hotfix/urgent-fix',
                phase: 'setup',
                required: true,
                order: 1,
            ),
            new GuideStep(
                id: 'fix',
                name: 'Apply Fix',
                description: 'Implement the urgent fix. Keep it minimal and focused.',
                command: null,
                phase: 'development',
                required: true,
                order: 2,
                prerequisite: 'create-branch',
                manualStep: true,
            ),
            new GuideStep(
                id: 'run-tests',
                name: 'Run Critical Tests',
                description: 'Run tests to ensure the fix works.',
                command: './vendor/bin/pest',
                phase: 'verification',
                required: true,
                order: 3,
                prerequisite: 'fix',
            ),
            new GuideStep(
                id: 'create-pr',
                name: 'Create Urgent PR',
                description: 'Create a pull request marked as urgent.',
                command: 'gh pr create --fill --label "urgent"',
                phase: 'review',
                required: true,
                order: 4,
                prerequisite: 'run-tests',
            ),
            new GuideStep(
                id: 'merge',
                name: 'Merge Immediately',
                description: 'Merge the hotfix after quick review.',
                command: 'gh pr merge --merge',
                phase: 'review',
                required: true,
                order: 5,
                prerequisite: 'create-pr',
            ),
            new GuideStep(
                id: 'deploy',
                name: 'Deploy',
                description: 'Deploy the hotfix to production.',
                command: null,
                phase: 'deployment',
                required: true,
                order: 6,
                prerequisite: 'merge',
                manualStep: true,
            ),
        ];
    }

    // ========================================
    // AUTO-DETECTION
    // ========================================

    private function detectStepCompletion(GuideStep $step): bool
    {
        return match ($step->id) {
            'init' => $this->isProjectInitialized(),
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
        return $this->filesystem->exists($this->workingDirectory.'/.git/hooks/pre-commit');
    }

    // ========================================
    // PERSISTENCE
    // ========================================

    private function loadProgress(): void
    {
        if ($this->filesystem->exists($this->progressPath)) {
            $data = Yaml::parseFile($this->progressPath);
            $this->progress = is_array($data) ? $data : [];
        } else {
            $this->progress = [
                'current_workflow' => null,
                'workflow_name' => null,
                'completed' => [],
                'skipped' => [],
                'history' => [],
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
