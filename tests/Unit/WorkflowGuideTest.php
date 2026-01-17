<?php

declare(strict_types=1);

use LaraForge\Guide\GuideStep;
use LaraForge\Guide\WorkflowGuide;
use LaraForge\Guide\WorkflowType;

describe('WorkflowGuide', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/laraforge-test-'.uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir.'/.laraforge', 0755, true);
        $this->guide = new WorkflowGuide($this->tempDir);
    });

    afterEach(function (): void {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tempDir);
    });

    it('detects uninitialized project', function (): void {
        expect($this->guide->isProjectInitialized())->toBeFalse();
    });

    it('detects initialized project', function (): void {
        file_put_contents($this->tempDir.'/.laraforge/project.yaml', 'project: test');

        $guide = new WorkflowGuide($this->tempDir);
        expect($guide->isProjectInitialized())->toBeTrue();
    });

    it('defaults to onboarding for uninitialized project', function (): void {
        expect($this->guide->currentWorkflowType())->toBe(WorkflowType::ONBOARDING);
    });

    it('returns null workflow type for initialized project with no active workflow', function (): void {
        file_put_contents($this->tempDir.'/.laraforge/project.yaml', 'project: test');

        $guide = new WorkflowGuide($this->tempDir);
        expect($guide->currentWorkflowType())->toBeNull();
    });

    it('can start a feature workflow', function (): void {
        file_put_contents($this->tempDir.'/.laraforge/project.yaml', 'project: test');

        $guide = new WorkflowGuide($this->tempDir);
        $guide->startWorkflow(WorkflowType::FEATURE, 'User Auth');

        expect($guide->currentWorkflowType())->toBe(WorkflowType::FEATURE);
        expect($guide->workflowName())->toBe('User Auth');
    });

    it('can start a bugfix workflow', function (): void {
        $this->guide->startWorkflow(WorkflowType::BUGFIX, 'Fix login bug');

        expect($this->guide->currentWorkflowType())->toBe(WorkflowType::BUGFIX);
        expect($this->guide->workflowName())->toBe('Fix login bug');
    });

    it('returns different steps for different workflow types', function (): void {
        $this->guide->startWorkflow(WorkflowType::FEATURE, 'Test');
        $featureSteps = $this->guide->allSteps();

        $this->guide->endWorkflow();
        $this->guide->startWorkflow(WorkflowType::BUGFIX, 'Test');
        $bugfixSteps = $this->guide->allSteps();

        expect(count($featureSteps))->toBeGreaterThan(count($bugfixSteps));
    });

    it('ends workflow and adds to history', function (): void {
        // Initialize project first so it doesn't default to onboarding
        file_put_contents($this->tempDir.'/.laraforge/project.yaml', 'project: test');
        $guide = new WorkflowGuide($this->tempDir);

        $guide->startWorkflow(WorkflowType::FEATURE, 'Test Feature');
        $guide->endWorkflow();

        expect($guide->currentWorkflowType())->toBeNull();
        expect($guide->history())->toHaveCount(1);
        expect($guide->history()[0]['name'])->toBe('Test Feature');
    });

    it('can mark steps completed', function (): void {
        $this->guide->startWorkflow(WorkflowType::BUGFIX, 'Test');
        $this->guide->markCompleted('create-branch');

        expect($this->guide->isStepCompleted('create-branch'))->toBeTrue();
    });

    it('calculates progress correctly', function (): void {
        $this->guide->startWorkflow(WorkflowType::BUGFIX, 'Test');

        expect($this->guide->progressPercentage())->toBe(0);

        $steps = $this->guide->allSteps();
        $this->guide->markCompleted($steps[0]->id);

        $expected = (int) round((1 / count($steps)) * 100);
        expect($this->guide->progressPercentage())->toBe($expected);
    });

    it('advances to next step after completing current', function (): void {
        $this->guide->startWorkflow(WorkflowType::BUGFIX, 'Test');

        $first = $this->guide->currentStep();
        $this->guide->markCompleted($first->id);

        $second = $this->guide->currentStep();
        expect($second->id)->not->toBe($first->id);
    });

    it('respects step prerequisites', function (): void {
        $this->guide->startWorkflow(WorkflowType::BUGFIX, 'Test');

        // First step should be create-branch
        $current = $this->guide->currentStep();
        expect($current->id)->toBe('create-branch');

        // Complete it
        $this->guide->markCompleted('create-branch');

        // Next should be write-test or fix-bug (they have create-branch as prerequisite)
        $next = $this->guide->currentStep();
        expect(in_array($next->id, ['write-test', 'fix-bug']))->toBeTrue();
    });
});

describe('WorkflowType', function (): void {
    it('has all expected workflow types', function (): void {
        expect(WorkflowType::cases())->toHaveCount(5);
        expect(WorkflowType::ONBOARDING)->not->toBeNull();
        expect(WorkflowType::FEATURE)->not->toBeNull();
        expect(WorkflowType::BUGFIX)->not->toBeNull();
        expect(WorkflowType::REFACTOR)->not->toBeNull();
        expect(WorkflowType::HOTFIX)->not->toBeNull();
    });

    it('provides labels for each type', function (): void {
        expect(WorkflowType::FEATURE->label())->toBe('New Feature');
        expect(WorkflowType::BUGFIX->label())->toBe('Bug Fix');
        expect(WorkflowType::HOTFIX->label())->toBe('Hotfix (Urgent)');
    });

    it('provides descriptions for each type', function (): void {
        expect(WorkflowType::FEATURE->description())->toContain('PRD');
        expect(WorkflowType::BUGFIX->description())->toContain('Bug fix');
    });

    it('provides icons for each type', function (): void {
        expect(WorkflowType::FEATURE->icon())->toBe('✨');
        expect(WorkflowType::BUGFIX->icon())->toBe('🐛');
        expect(WorkflowType::HOTFIX->icon())->toBe('🔥');
    });

    it('returns types for existing project', function (): void {
        $types = WorkflowType::forExistingProject();

        expect($types)->toContain(WorkflowType::FEATURE);
        expect($types)->toContain(WorkflowType::BUGFIX);
        expect($types)->not->toContain(WorkflowType::ONBOARDING);
    });
});

describe('GuideStep', function (): void {
    it('creates a step with all properties', function (): void {
        $step = new GuideStep(
            id: 'test-step',
            name: 'Test Step',
            description: 'A test step',
            command: 'echo test',
            phase: 'testing',
            required: true,
            order: 1,
        );

        expect($step->id)->toBe('test-step');
        expect($step->name)->toBe('Test Step');
        expect($step->command)->toBe('echo test');
        expect($step->required)->toBeTrue();
    });

    it('reports if step can be skipped', function (): void {
        $required = new GuideStep('r', 'Required', '', 'cmd', 'p', true, 1);
        $optional = new GuideStep('o', 'Optional', '', 'cmd', 'p', false, 2);

        expect($required->canSkip())->toBeFalse();
        expect($optional->canSkip())->toBeTrue();
    });

    it('reports if step has command', function (): void {
        $withCmd = new GuideStep('a', 'A', '', 'cmd', 'p', true, 1);
        $manual = new GuideStep('b', 'B', '', null, 'p', true, 2, manualStep: true);
        $noCmd = new GuideStep('c', 'C', '', null, 'p', true, 3);

        expect($withCmd->hasCommand())->toBeTrue();
        expect($manual->hasCommand())->toBeFalse();
        expect($noCmd->hasCommand())->toBeFalse();
    });

    it('converts to array', function (): void {
        $step = new GuideStep('id', 'Name', 'Desc', 'cmd', 'phase', true, 1);
        $array = $step->toArray();

        expect($array)->toHaveKey('id');
        expect($array)->toHaveKey('name');
        expect($array)->toHaveKey('command');
        expect($array['id'])->toBe('id');
    });
});
