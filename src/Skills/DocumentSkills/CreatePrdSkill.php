<?php

declare(strict_types=1);

namespace LaraForge\Skills\DocumentSkills;

use LaraForge\Documents\DocumentRegistry;
use LaraForge\Documents\ProductRequirements;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;

class CreatePrdSkill extends Skill
{
    public function identifier(): string
    {
        return 'create-prd';
    }

    public function name(): string
    {
        return 'Create PRD';
    }

    public function description(): string
    {
        return 'Creates a Product Requirements Document (PRD) for a feature';
    }

    public function parameters(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'description' => 'The PRD title',
                'required' => true,
            ],
            'feature_id' => [
                'type' => 'string',
                'description' => 'Associated feature identifier',
                'required' => false,
            ],
            'problem_statement' => [
                'type' => 'string',
                'description' => 'The problem being solved',
                'required' => false,
            ],
            'target_audience' => [
                'type' => 'string',
                'description' => 'Who this feature is for',
                'required' => false,
            ],
            'objectives' => [
                'type' => 'array',
                'description' => 'List of objectives with id, description, priority',
                'required' => false,
                'default' => [],
            ],
            'requirements' => [
                'type' => 'array',
                'description' => 'List of requirements with id, description, priority',
                'required' => false,
                'default' => [],
            ],
            'user_stories' => [
                'type' => 'array',
                'description' => 'List of user stories with id, description',
                'required' => false,
                'default' => [],
            ],
            'constraints' => [
                'type' => 'array',
                'description' => 'List of constraints',
                'required' => false,
                'default' => [],
            ],
            'assumptions' => [
                'type' => 'array',
                'description' => 'List of assumptions',
                'required' => false,
                'default' => [],
            ],
            'out_of_scope' => [
                'type' => 'array',
                'description' => 'Items explicitly out of scope',
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
        return ['documentation', 'requirements', 'prd', 'planning'];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $prd = new ProductRequirements(
            title: $params['title'],
            featureId: $params['feature_id'] ?? null,
        );

        if (isset($params['problem_statement'])) {
            $prd->setProblemStatement($params['problem_statement']);
        }

        if (isset($params['target_audience'])) {
            $prd->setTargetAudience($params['target_audience']);
        }

        foreach ($params['objectives'] ?? [] as $objective) {
            $prd->addObjective(
                $objective['id'] ?? 'OBJ-'.uniqid(),
                $objective['description'],
                $objective['priority'] ?? 'medium',
                $objective['rationale'] ?? null
            );
        }

        foreach ($params['requirements'] ?? [] as $requirement) {
            $prd->addRequirement(
                $requirement['id'] ?? 'REQ-'.uniqid(),
                $requirement['description'],
                $requirement['priority'] ?? 'medium'
            );
        }

        foreach ($params['user_stories'] ?? [] as $story) {
            $prd->addUserStory(
                $story['id'] ?? 'US-'.uniqid(),
                $story['description']
            );
        }

        foreach ($params['constraints'] ?? [] as $constraint) {
            $prd->addConstraint($constraint);
        }

        foreach ($params['assumptions'] ?? [] as $assumption) {
            $prd->addAssumption($assumption);
        }

        foreach ($params['out_of_scope'] ?? [] as $item) {
            $prd->addOutOfScope($item);
        }

        // Validate the document
        if (! $prd->isValid()) {
            return SkillResult::failure(
                'PRD validation failed: '.implode(', ', $prd->validationErrors()),
                metadata: ['validation_errors' => $prd->validationErrors()]
            );
        }

        // Save the document
        $docsDir = $context->docsDir();
        $registry = new DocumentRegistry($docsDir);
        $path = $registry->save($prd);

        // Update feature if exists
        $feature = $context->currentFeature();
        if ($feature) {
            $feature->addDocument('prd', $path);
        }

        return SkillResult::success(
            output: $path,
            artifacts: [
                'prd_path' => $path,
                'document' => $prd->toArray(),
            ],
            nextSteps: [
                [
                    'skill' => 'create-frd',
                    'params' => ['prd_path' => $path, 'feature_id' => $params['feature_id'] ?? null],
                    'reason' => 'Create detailed Feature Requirements Document from PRD',
                ],
            ],
            metadata: [
                'objectives_count' => count($prd->objectives()),
                'requirements_count' => count($prd->requirements()),
            ]
        );
    }
}
