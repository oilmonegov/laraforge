<?php

declare(strict_types=1);

use LaraForge\Project\ProjectContext;
use LaraForge\Project\ProjectState;
use LaraForge\Workflows\Step;
use LaraForge\Workflows\StepResult;
use LaraForge\Workflows\Workflow;
use Symfony\Component\Filesystem\Filesystem;

class WorkflowTestStep extends Step
{
    public function __construct(
        private readonly string $id,
        private readonly string $stepName,
        private readonly array $requiredInputsList = [],
        private readonly array $expectedOutputsList = [],
        private readonly bool $canExecuteValue = true,
    ) {}

    public function identifier(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->stepName;
    }

    public function description(): string
    {
        return "Test step: {$this->stepName}";
    }

    public function agentRole(): string
    {
        return 'developer';
    }

    public function skills(): array
    {
        return [];
    }

    public function canExecute(ProjectContext $context): bool
    {
        return $this->canExecuteValue;
    }

    public function isComplete(ProjectContext $context): bool
    {
        $completedSteps = $context->get('completed_steps', []);

        return in_array($this->id, $completedSteps, true);
    }

    public function requiredInputs(): array
    {
        return $this->requiredInputsList;
    }

    public function expectedOutputs(): array
    {
        return $this->expectedOutputsList;
    }

    protected function perform(ProjectContext $context): StepResult
    {
        return StepResult::success($this);
    }
}

class WorkflowTestWorkflow extends Workflow
{
    private array $stepConfigs;

    public function __construct(array $stepConfigs = [])
    {
        $this->stepConfigs = $stepConfigs ?: [
            ['id' => 'step-1', 'name' => 'Step 1'],
            ['id' => 'step-2', 'name' => 'Step 2'],
            ['id' => 'step-3', 'name' => 'Step 3'],
        ];
    }

    public function identifier(): string
    {
        return 'test-workflow';
    }

    public function name(): string
    {
        return 'Test Workflow';
    }

    public function description(): string
    {
        return 'A test workflow';
    }

    protected function createSteps(): array
    {
        return array_map(
            fn ($config) => new WorkflowTestStep(
                $config['id'],
                $config['name'],
                $config['required'] ?? [],
                $config['expected'] ?? [],
                $config['canExecute'] ?? true,
            ),
            $this->stepConfigs
        );
    }
}

describe('StepResult', function () {
    it('creates a successful result', function () {
        $step = new WorkflowTestStep('step-1', 'Step 1');
        $result = StepResult::success(
            step: $step,
            outputs: ['file' => '/path/to/file'],
            artifacts: ['doc' => '/path/to/doc'],
            metadata: ['key' => 'value'],
        );

        expect($result->isSuccess())->toBeTrue()
            ->and($result->step())->toBe($step)
            ->and($result->outputs())->toHaveKey('file')
            ->and($result->artifacts())->toHaveKey('doc')
            ->and($result->error())->toBeNull()
            ->and($result->needsReview())->toBeFalse()
            ->and($result->getOutput('file'))->toBe('/path/to/file')
            ->and($result->getOutput('missing', 'default'))->toBe('default');
    });

    it('creates a failure result', function () {
        $step = new WorkflowTestStep('step-1', 'Step 1');
        $result = StepResult::failure(
            step: $step,
            error: 'Something went wrong',
            outputs: ['partial' => 'data'],
        );

        expect($result->isSuccess())->toBeFalse()
            ->and($result->error())->toBe('Something went wrong')
            ->and($result->outputs())->toHaveKey('partial');
    });

    it('creates a needs-review result', function () {
        $step = new WorkflowTestStep('step-1', 'Step 1');
        $result = StepResult::forReview(
            step: $step,
            reviewNotes: 'Please review the output',
            outputs: ['draft' => 'content'],
        );

        expect($result->isSuccess())->toBeTrue()
            ->and($result->needsReview())->toBeTrue()
            ->and($result->reviewNotes())->toBe('Please review the output');
    });

    it('converts to array', function () {
        $step = new WorkflowTestStep('step-1', 'Step 1');
        $result = StepResult::success($step, ['key' => 'value']);

        $array = $result->toArray();

        expect($array)->toHaveKey('success')
            ->and($array['success'])->toBeTrue()
            ->and($array)->toHaveKey('step')
            ->and($array['step'])->toBe('step-1')
            ->and($array)->toHaveKey('outputs')
            ->and($array)->toHaveKey('artifacts')
            ->and($array)->toHaveKey('error')
            ->and($array)->toHaveKey('needs_review')
            ->and($array)->toHaveKey('review_notes')
            ->and($array)->toHaveKey('metadata');
    });
});

describe('Workflow', function () {
    beforeEach(function () {
        $this->tempDir = createTempDirectory();
        $this->filesystem = new Filesystem;
        $this->state = ProjectState::initialize($this->tempDir, 'Test');
        $this->laraforge = laraforge($this->tempDir);
    });

    afterEach(function () {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    });

    it('returns workflow metadata', function () {
        $workflow = new WorkflowTestWorkflow;

        expect($workflow->identifier())->toBe('test-workflow')
            ->and($workflow->name())->toBe('Test Workflow')
            ->and($workflow->description())->toBe('A test workflow');
    });

    it('returns all steps', function () {
        $workflow = new WorkflowTestWorkflow;

        $steps = $workflow->steps();

        expect($steps)->toHaveCount(3)
            ->and($steps[0]->identifier())->toBe('step-1')
            ->and($steps[1]->identifier())->toBe('step-2')
            ->and($steps[2]->identifier())->toBe('step-3');
    });

    it('gets current step', function () {
        $workflow = new WorkflowTestWorkflow;
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );

        $current = $workflow->currentStep($context);

        expect($current)->not->toBeNull()
            ->and($current->identifier())->toBe('step-1');
    });

    it('advances current step when previous completed', function () {
        $workflow = new WorkflowTestWorkflow;
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );

        // Mark first step complete
        $context->set('completed_steps', ['step-1']);

        $current = $workflow->currentStep($context);

        expect($current->identifier())->toBe('step-2');
    });

    it('returns null current step when all complete', function () {
        $workflow = new WorkflowTestWorkflow;
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );

        $context->set('completed_steps', ['step-1', 'step-2', 'step-3']);

        $current = $workflow->currentStep($context);

        expect($current)->toBeNull();
    });

    it('checks if workflow can start', function () {
        $workflow = new WorkflowTestWorkflow;
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );

        expect($workflow->canStart($context))->toBeTrue();

        $blockedWorkflow = new WorkflowTestWorkflow([
            ['id' => 'blocked', 'name' => 'Blocked', 'canExecute' => false],
        ]);

        expect($blockedWorkflow->canStart($context))->toBeFalse();
    });

    it('checks if workflow is complete', function () {
        $workflow = new WorkflowTestWorkflow;
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );

        expect($workflow->isComplete($context))->toBeFalse();

        $context->set('completed_steps', ['step-1', 'step-2', 'step-3']);

        expect($workflow->isComplete($context))->toBeTrue();
    });

    it('calculates progress', function () {
        $workflow = new WorkflowTestWorkflow;
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );

        expect($workflow->progress($context))->toBe(0);

        $context->set('completed_steps', ['step-1']);

        expect($workflow->progress($context))->toBe(33);

        $context->set('completed_steps', ['step-1', 'step-2']);

        expect($workflow->progress($context))->toBe(67);

        $context->set('completed_steps', ['step-1', 'step-2', 'step-3']);

        expect($workflow->progress($context))->toBe(100);
    });

    it('gets step by identifier', function () {
        $workflow = new WorkflowTestWorkflow;

        $step = $workflow->getStep('step-2');

        expect($step)->not->toBeNull()
            ->and($step->identifier())->toBe('step-2')
            ->and($step->name())->toBe('Step 2');

        $nonExistent = $workflow->getStep('non-existent');

        expect($nonExistent)->toBeNull();
    });

    it('tracks step completion', function () {
        $workflow = new WorkflowTestWorkflow;
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );

        $step = $workflow->getStep('step-1');
        $workflow->onStepComplete($step, $context);

        $completedSteps = $context->get('completed_steps', []);

        expect($completedSteps)->toContain('step-1');
    });

    it('returns workflow status', function () {
        $workflow = new WorkflowTestWorkflow;
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );

        $context->set('completed_steps', ['step-1']);

        $status = $workflow->status($context);

        expect($status)->toHaveKey('workflow')
            ->and($status['workflow'])->toBe('test-workflow')
            ->and($status)->toHaveKey('progress')
            ->and($status['progress'])->toBe(33)
            ->and($status)->toHaveKey('is_complete')
            ->and($status['is_complete'])->toBeFalse()
            ->and($status)->toHaveKey('current_step')
            ->and($status['current_step'])->toBe('step-2')
            ->and($status)->toHaveKey('steps')
            ->and($status['steps'])->toHaveKey('step-1')
            ->and($status['steps']['step-1']['completed'])->toBeTrue()
            ->and($status['steps']['step-2']['current'])->toBeTrue();
    });
});
