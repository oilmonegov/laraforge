<?php

declare(strict_types=1);

namespace LaraForge\Skills\DocumentSkills;

use LaraForge\Documents\DesignDocument;
use LaraForge\Documents\DocumentRegistry;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;

class CreatePseudocodeSkill extends Skill
{
    public function identifier(): string
    {
        return 'create-pseudocode';
    }

    public function name(): string
    {
        return 'Create Pseudocode';
    }

    public function description(): string
    {
        return 'Creates a design document with pseudocode and architectural decisions';
    }

    public function parameters(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'description' => 'The design document title',
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
            'overview' => [
                'type' => 'string',
                'description' => 'High-level overview of the design',
                'required' => false,
            ],
            'components' => [
                'type' => 'array',
                'description' => 'List of components with name, purpose, interfaces, dependencies',
                'required' => false,
                'default' => [],
            ],
            'pseudocode' => [
                'type' => 'object',
                'description' => 'Map of component names to pseudocode',
                'required' => false,
                'default' => [],
            ],
            'data_flows' => [
                'type' => 'array',
                'description' => 'List of data flows with from, to, description',
                'required' => false,
                'default' => [],
            ],
            'architectural_decisions' => [
                'type' => 'array',
                'description' => 'List of architectural decisions with id, description, options, decision, rationale',
                'required' => false,
                'default' => [],
            ],
            'file_structure' => [
                'type' => 'object',
                'description' => 'Map of file names to {path, purpose, creates, modifies}',
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
        return ['documentation', 'design', 'pseudocode', 'architecture'];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $design = new DesignDocument(
            title: $params['title'],
            featureId: $params['feature_id'] ?? null,
        );

        if (isset($params['frd_path'])) {
            $design->setFrdReference($params['frd_path']);
        }

        if (isset($params['overview'])) {
            $design->setOverview($params['overview']);
        }

        // Add components
        foreach ($params['components'] ?? [] as $component) {
            $design->addComponent(
                $component['name'],
                $component['purpose'],
                $component['interfaces'] ?? [],
                $component['dependencies'] ?? []
            );
        }

        // Add pseudocode for each component
        foreach ($params['pseudocode'] ?? [] as $componentName => $code) {
            $design->setPseudocodeFor($componentName, $code);
        }

        // Add data flows
        foreach ($params['data_flows'] ?? [] as $flow) {
            $design->addDataFlow(
                $flow['from'],
                $flow['to'],
                $flow['description'] ?? null
            );
        }

        // Add architectural decisions
        foreach ($params['architectural_decisions'] ?? [] as $decision) {
            $design->addArchitecturalDecision(
                $decision['id'] ?? 'ADR-'.uniqid(),
                $decision['description'],
                $decision['options'] ?? [],
                $decision['decision'] ?? null,
                $decision['rationale'] ?? null
            );
        }

        // Add file structure
        foreach ($params['file_structure'] ?? [] as $name => $file) {
            $design->addFileToStructure(
                $name,
                $file['path'],
                $file['purpose'],
                $file['creates'] ?? [],
                $file['modifies'] ?? []
            );
        }

        // Validate the document
        if (! $design->isValid()) {
            return SkillResult::failure(
                'Design document validation failed: '.implode(', ', $design->validationErrors()),
                metadata: ['validation_errors' => $design->validationErrors()]
            );
        }

        // Save the document
        $docsDir = $context->docsDir();
        $registry = new DocumentRegistry($docsDir);
        $path = $registry->save($design);

        // Update feature if exists
        $feature = $context->currentFeature();
        if ($feature) {
            $feature->addDocument('design', $path);
        }

        return SkillResult::success(
            output: $path,
            artifacts: [
                'design_path' => $path,
                'document' => $design->toArray(),
            ],
            nextSteps: [
                [
                    'skill' => 'create-test-contract',
                    'params' => ['design_path' => $path, 'feature_id' => $params['feature_id'] ?? null],
                    'reason' => 'Create test contracts from design document',
                ],
                [
                    'skill' => 'create-branch',
                    'params' => ['feature_id' => $params['feature_id'] ?? null],
                    'reason' => 'Create feature branch for implementation',
                ],
            ],
            metadata: [
                'components_count' => count($design->components()),
                'has_pseudocode' => ! empty($design->pseudocode()),
                'decisions_count' => count($design->architecturalDecisions()),
            ]
        );
    }
}
