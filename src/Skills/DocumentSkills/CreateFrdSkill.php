<?php

declare(strict_types=1);

namespace LaraForge\Skills\DocumentSkills;

use LaraForge\Documents\DocumentRegistry;
use LaraForge\Documents\FeatureRequirements;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;

class CreateFrdSkill extends Skill
{
    public function identifier(): string
    {
        return 'create-frd';
    }

    public function name(): string
    {
        return 'Create FRD';
    }

    public function description(): string
    {
        return 'Creates a Feature Requirements Document (FRD) with stepwise refinement';
    }

    public function parameters(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'description' => 'The feature title',
                'required' => true,
            ],
            'feature_id' => [
                'type' => 'string',
                'description' => 'Associated feature identifier',
                'required' => false,
            ],
            'prd_path' => [
                'type' => 'string',
                'description' => 'Path to the related PRD document',
                'required' => false,
            ],
            'requirements' => [
                'type' => 'array',
                'description' => 'List of requirements with id, description, priority, acceptance_criteria',
                'required' => true,
            ],
            'stepwise_refinement' => [
                'type' => 'object',
                'description' => 'Stepwise refinement levels (level_1, level_2, etc.)',
                'required' => false,
                'default' => [],
            ],
            'pseudocode' => [
                'type' => 'string',
                'description' => 'Pseudocode for the feature',
                'required' => false,
            ],
            'dependencies' => [
                'type' => 'array',
                'description' => 'List of dependencies',
                'required' => false,
                'default' => [],
            ],
            'technical_notes' => [
                'type' => 'object',
                'description' => 'Technical notes and considerations',
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
        return ['documentation', 'requirements', 'frd', 'planning', 'stepwise-refinement'];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $frd = new FeatureRequirements(
            title: $params['title'],
            featureId: $params['feature_id'] ?? null,
        );

        if (isset($params['prd_path'])) {
            $frd->setPrdReference($params['prd_path']);
        }

        // Add requirements
        foreach ($params['requirements'] as $requirement) {
            $frd->addRequirement(
                $requirement['id'] ?? 'REQ-'.uniqid(),
                $requirement['description'],
                $requirement['priority'] ?? 'medium',
                $requirement['acceptance_criteria'] ?? []
            );
        }

        // Add stepwise refinement
        if (! empty($params['stepwise_refinement'])) {
            foreach ($params['stepwise_refinement'] as $level => $steps) {
                if (is_array($steps)) {
                    // Check if it's a simple list or a map of step names to substeps
                    $firstKey = array_key_first($steps);
                    if (is_string($firstKey) && is_array($steps[$firstKey])) {
                        // Map of step names to substeps
                        foreach ($steps as $stepName => $substeps) {
                            $frd->addRefinedStep($level, $stepName, $substeps);
                        }
                    } else {
                        // Simple list of steps
                        $frd->addRefinementLevel($level, $steps);
                    }
                }
            }
        }

        // Add pseudocode if provided
        if (isset($params['pseudocode'])) {
            $frd->setPseudocode($params['pseudocode']);
        }

        // Add dependencies
        foreach ($params['dependencies'] ?? [] as $dependency) {
            $frd->addDependency($dependency);
        }

        // Add technical notes
        foreach ($params['technical_notes'] ?? [] as $key => $value) {
            $frd->setTechnicalNote($key, $value);
        }

        // Validate the document
        if (! $frd->isValid()) {
            return SkillResult::failure(
                'FRD validation failed: '.implode(', ', $frd->validationErrors()),
                metadata: ['validation_errors' => $frd->validationErrors()]
            );
        }

        // Save the document
        $docsDir = $context->docsDir();
        $registry = new DocumentRegistry($docsDir);
        $path = $registry->save($frd);

        // Update feature if exists
        $feature = $context->currentFeature();
        if ($feature) {
            $feature->addDocument('frd', $path);
        }

        return SkillResult::success(
            output: $path,
            artifacts: [
                'frd_path' => $path,
                'document' => $frd->toArray(),
            ],
            nextSteps: [
                [
                    'skill' => 'create-pseudocode',
                    'params' => ['frd_path' => $path, 'feature_id' => $params['feature_id'] ?? null],
                    'reason' => 'Create detailed pseudocode from FRD requirements',
                ],
                [
                    'skill' => 'create-test-contract',
                    'params' => ['frd_path' => $path, 'feature_id' => $params['feature_id'] ?? null],
                    'reason' => 'Create test contracts from FRD requirements',
                ],
            ],
            metadata: [
                'requirements_count' => count($frd->requirements()),
                'has_stepwise_refinement' => ! empty($frd->stepwiseRefinement()),
                'has_pseudocode' => ! empty($frd->pseudocode()),
            ]
        );
    }
}
