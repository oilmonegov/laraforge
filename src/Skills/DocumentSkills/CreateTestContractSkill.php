<?php

declare(strict_types=1);

namespace LaraForge\Skills\DocumentSkills;

use LaraForge\Documents\DocumentRegistry;
use LaraForge\Documents\TestContract;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;

class CreateTestContractSkill extends Skill
{
    public function identifier(): string
    {
        return 'create-test-contract';
    }

    public function name(): string
    {
        return 'Create Test Contract';
    }

    public function description(): string
    {
        return 'Creates test contracts/specifications (not implementations) for a feature';
    }

    public function parameters(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'description' => 'The test contract document title',
                'required' => true,
            ],
            'feature_id' => [
                'type' => 'string',
                'description' => 'Associated feature identifier',
                'required' => false,
            ],
            'frd_path' => [
                'type' => 'string',
                'description' => 'Path to the related FRD document',
                'required' => false,
            ],
            'contracts' => [
                'type' => 'array',
                'description' => 'List of test contracts with name, type, preconditions, action, expectations, invariants',
                'required' => true,
            ],
            'global_invariants' => [
                'type' => 'array',
                'description' => 'Invariants that apply to all tests',
                'required' => false,
                'default' => [],
            ],
            'setup_requirements' => [
                'type' => 'object',
                'description' => 'Requirements for test setup',
                'required' => false,
                'default' => [],
            ],
        ];
    }

    public function category(): string
    {
        return 'document';
    }

    public function tags(): array
    {
        return ['documentation', 'testing', 'contracts', 'specifications'];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $testContract = new TestContract(
            title: $params['title'],
            featureId: $params['feature_id'] ?? null,
        );

        if (isset($params['frd_path'])) {
            $testContract->setFrdReference($params['frd_path']);
        }

        // Add contracts
        foreach ($params['contracts'] as $contract) {
            // Check if using Given-When-Then format
            if (isset($contract['given']) && isset($contract['when']) && isset($contract['then'])) {
                $testContract->addGwtContract(
                    $contract['name'],
                    $contract['given'],
                    $contract['when'],
                    $contract['then'],
                    $contract['type'] ?? 'feature',
                    $contract['invariants'] ?? []
                );
            } else {
                $testContract->addContract(
                    $contract['name'],
                    $contract['type'] ?? 'feature',
                    $contract['preconditions'] ?? [],
                    $contract['action'] ?? null,
                    $contract['expectations'] ?? [],
                    $contract['invariants'] ?? []
                );
            }
        }

        // Add global invariants
        foreach ($params['global_invariants'] ?? [] as $invariant) {
            $testContract->addGlobalInvariant($invariant);
        }

        // Add setup requirements
        foreach ($params['setup_requirements'] ?? [] as $key => $value) {
            $testContract->setSetupRequirement($key, $value);
        }

        // Validate the document
        if (! $testContract->isValid()) {
            return SkillResult::failure(
                'Test contract validation failed: '.implode(', ', $testContract->validationErrors()),
                metadata: ['validation_errors' => $testContract->validationErrors()]
            );
        }

        // Save the document
        $docsDir = $context->docsDir();
        $registry = new DocumentRegistry($docsDir);
        $path = $registry->save($testContract);

        // Update feature if exists
        $feature = $context->currentFeature();
        if ($feature) {
            $feature->addDocument('test-contract', $path);
        }

        $summary = $testContract->summary();

        return SkillResult::success(
            output: $path,
            artifacts: [
                'test_contract_path' => $path,
                'document' => $testContract->toArray(),
                'summary' => $summary,
            ],
            nextSteps: [
                [
                    'skill' => 'feature-test',
                    'params' => ['feature' => $params['title'], 'criteria_file' => $path],
                    'reason' => 'Generate test implementations from contracts',
                ],
            ],
            metadata: [
                'contracts_count' => $summary['total'],
                'by_type' => $summary['by_type'],
                'has_global_invariants' => ! empty($testContract->globalInvariants()),
            ]
        );
    }
}
