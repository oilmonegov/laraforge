<?php

declare(strict_types=1);

namespace LaraForge\Workflows;

use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\SkillRegistry;
use LaraForge\Workflows\Contracts\StepInterface;
use LaraForge\Workflows\Contracts\StepResultInterface;
use LaraForge\Workflows\Contracts\WorkflowInterface;

class WorkflowOrchestrator
{
    /**
     * @var array<string, WorkflowInterface>
     */
    private array $workflows = [];

    public function __construct(
        private readonly LaraForgeInterface $laraforge,
        private readonly SkillRegistry $skills,
    ) {}

    public function register(WorkflowInterface $workflow): void
    {
        if ($workflow instanceof Workflow) {
            $workflow->setLaraForge($this->laraforge);
            $workflow->setSkillRegistry($this->skills);
        }

        $this->workflows[$workflow->identifier()] = $workflow;
    }

    public function get(string $identifier): ?WorkflowInterface
    {
        return $this->workflows[$identifier] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->workflows[$identifier]);
    }

    /**
     * @return array<string, WorkflowInterface>
     */
    public function all(): array
    {
        return $this->workflows;
    }

    /**
     * Analyze the current context and recommend next actions.
     */
    public function analyze(ProjectContext $context): Recommendation
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return new Recommendation(
                type: 'action',
                agent: 'pm',
                skill: 'feature-start',
                message: 'No active feature. Start by defining a feature to work on.',
                workflow: null,
                step: null,
            );
        }

        $phase = $feature->phase();

        return match ($phase) {
            'planning', 'new' => $this->recommendForPlanning($context),
            'requirements' => $this->recommendForRequirements($context),
            'design' => $this->recommendForDesign($context),
            'implementation' => $this->recommendForImplementation($context),
            'testing' => $this->recommendForTesting($context),
            'review' => $this->recommendForReview($context),
            default => $this->detectPhaseAndRecommend($context),
        };
    }

    /**
     * Execute a workflow step.
     */
    public function executeStep(
        WorkflowInterface $workflow,
        StepInterface $step,
        ProjectContext $context,
    ): StepResultInterface {
        $result = $step->execute($context);

        if ($result->isSuccess()) {
            $workflow->onStepComplete($step, $context);
        }

        return $result;
    }

    /**
     * Run a workflow from the beginning or current step.
     *
     * @return array<StepResultInterface>
     */
    public function runWorkflow(
        WorkflowInterface $workflow,
        ProjectContext $context,
        bool $stopOnReview = true,
    ): array {
        $results = [];

        if (! $workflow->canStart($context)) {
            return $results;
        }

        $workflow->onStart($context);

        while (! $workflow->isComplete($context)) {
            $step = $workflow->nextStep($context);

            if (! $step) {
                break;
            }

            $result = $this->executeStep($workflow, $step, $context);
            $results[] = $result;

            if (! $result->isSuccess()) {
                break;
            }

            if ($stopOnReview && $result->needsReview()) {
                break;
            }
        }

        if ($workflow->isComplete($context)) {
            $workflow->onComplete($context);
        }

        return $results;
    }

    /**
     * Find the best workflow for a given task type.
     */
    public function findWorkflowFor(string $taskType): ?WorkflowInterface
    {
        $mapping = [
            'feature' => 'feature',
            'bugfix' => 'bugfix',
            'refactor' => 'refactor',
            'test' => 'feature',  // Use feature workflow for tests
        ];

        $workflowId = $mapping[$taskType] ?? null;

        return $workflowId ? $this->get($workflowId) : null;
    }

    /**
     * Get workflow status for all registered workflows.
     *
     * @return array<string, array>
     */
    public function allStatuses(ProjectContext $context): array
    {
        $statuses = [];
        foreach ($this->workflows as $id => $workflow) {
            $statuses[$id] = $workflow instanceof Workflow
                ? $workflow->status($context)
                : ['workflow' => $id];
        }

        return $statuses;
    }

    private function recommendForPlanning(ProjectContext $context): Recommendation
    {
        $feature = $context->currentFeature();
        $prdExists = $feature?->document('prd') !== null;

        if (! $prdExists) {
            return new Recommendation(
                type: 'skill',
                agent: 'analyst',
                skill: 'create-prd',
                message: 'Start by creating a Product Requirements Document (PRD) to define objectives and scope.',
                workflow: 'feature',
                step: 'requirements',
            );
        }

        return new Recommendation(
            type: 'skill',
            agent: 'analyst',
            skill: 'create-frd',
            message: 'Create a Feature Requirements Document (FRD) with detailed requirements and acceptance criteria.',
            workflow: 'feature',
            step: 'requirements',
        );
    }

    private function recommendForRequirements(ProjectContext $context): Recommendation
    {
        $feature = $context->currentFeature();
        $frdExists = $feature?->document('frd') !== null;

        if (! $frdExists) {
            return new Recommendation(
                type: 'skill',
                agent: 'analyst',
                skill: 'create-frd',
                message: 'Create a Feature Requirements Document with stepwise refinement.',
                workflow: 'feature',
                step: 'requirements',
            );
        }

        return new Recommendation(
            type: 'action',
            agent: 'architect',
            skill: 'create-pseudocode',
            message: 'Requirements are complete. Move to design phase with pseudocode.',
            workflow: 'feature',
            step: 'design',
        );
    }

    private function recommendForDesign(ProjectContext $context): Recommendation
    {
        $feature = $context->currentFeature();
        $designExists = $feature?->document('design') !== null;
        $testContractExists = $feature?->document('test-contract') !== null;

        if (! $designExists) {
            return new Recommendation(
                type: 'skill',
                agent: 'architect',
                skill: 'create-pseudocode',
                message: 'Create design document with pseudocode from the FRD.',
                workflow: 'feature',
                step: 'design',
            );
        }

        if (! $testContractExists) {
            return new Recommendation(
                type: 'skill',
                agent: 'architect',
                skill: 'create-test-contract',
                message: 'Create test contracts before implementation.',
                workflow: 'feature',
                step: 'test-contract',
            );
        }

        return new Recommendation(
            type: 'action',
            agent: 'developer',
            skill: 'implement',
            message: 'Design is complete. Ready for implementation.',
            workflow: 'feature',
            step: 'implement',
        );
    }

    private function recommendForImplementation(ProjectContext $context): Recommendation
    {
        return new Recommendation(
            type: 'skill',
            agent: 'developer',
            skill: 'implement',
            message: 'Continue implementation based on pseudocode and test contracts.',
            workflow: 'feature',
            step: 'implement',
        );
    }

    private function recommendForTesting(ProjectContext $context): Recommendation
    {
        return new Recommendation(
            type: 'skill',
            agent: 'tester',
            skill: 'validate-tests',
            message: 'Validate that tests match the test contracts.',
            workflow: 'feature',
            step: 'verify',
        );
    }

    private function recommendForReview(ProjectContext $context): Recommendation
    {
        return new Recommendation(
            type: 'skill',
            agent: 'reviewer',
            skill: 'review-code',
            message: 'Review the implementation for quality and adherence to design.',
            workflow: 'feature',
            step: 'review',
        );
    }

    private function detectPhaseAndRecommend(ProjectContext $context): Recommendation
    {
        $feature = $context->currentFeature();

        if (! $feature) {
            return $this->recommendForPlanning($context);
        }

        // Detect phase from documents
        $hasPrd = $feature->document('prd') !== null;
        $hasFrd = $feature->document('frd') !== null;
        $hasDesign = $feature->document('design') !== null;
        $hasTestContract = $feature->document('test-contract') !== null;

        if (! $hasPrd && ! $hasFrd) {
            return $this->recommendForPlanning($context);
        }

        if (! $hasDesign) {
            return $this->recommendForDesign($context);
        }

        if (! $hasTestContract) {
            return new Recommendation(
                type: 'skill',
                agent: 'architect',
                skill: 'create-test-contract',
                message: 'Create test contracts before implementation.',
                workflow: 'feature',
                step: 'test-contract',
            );
        }

        return $this->recommendForImplementation($context);
    }
}

final class Recommendation
{
    public function __construct(
        public readonly string $type,      // 'skill', 'action', 'review'
        public readonly string $agent,
        public readonly string $skill,
        public readonly string $message,
        public readonly ?string $workflow,
        public readonly ?string $step,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'agent' => $this->agent,
            'skill' => $this->skill,
            'message' => $this->message,
            'workflow' => $this->workflow,
            'step' => $this->step,
        ];
    }
}
