<?php

declare(strict_types=1);

namespace LaraForge\Documents;

class ProductRequirements extends Document
{
    public function type(): string
    {
        return 'prd';
    }

    /**
     * @return array<array{id: string, description: string, priority?: string, rationale?: string}>
     */
    public function objectives(): array
    {
        return $this->content['objectives'] ?? [];
    }

    public function addObjective(string $id, string $description, string $priority = 'medium', ?string $rationale = null): void
    {
        $this->content['objectives'][] = array_filter([
            'id' => $id,
            'description' => $description,
            'priority' => $priority,
            'rationale' => $rationale,
        ]);
    }

    /**
     * @return array<array{id: string, description: string}>
     */
    public function userStories(): array
    {
        return $this->content['user_stories'] ?? [];
    }

    public function addUserStory(string $id, string $description): void
    {
        $this->content['user_stories'][] = [
            'id' => $id,
            'description' => $description,
        ];
    }

    /**
     * @return array<array{id: string, description: string, priority?: string}>
     */
    public function requirements(): array
    {
        return $this->content['requirements'] ?? [];
    }

    public function addRequirement(string $id, string $description, string $priority = 'medium'): void
    {
        $this->content['requirements'][] = [
            'id' => $id,
            'description' => $description,
            'priority' => $priority,
        ];
    }

    /**
     * @return array<string>
     */
    public function constraints(): array
    {
        return $this->content['constraints'] ?? [];
    }

    public function addConstraint(string $constraint): void
    {
        $this->content['constraints'][] = $constraint;
    }

    /**
     * @return array<string>
     */
    public function assumptions(): array
    {
        return $this->content['assumptions'] ?? [];
    }

    public function addAssumption(string $assumption): void
    {
        $this->content['assumptions'][] = $assumption;
    }

    /**
     * @return array<string>
     */
    public function outOfScope(): array
    {
        return $this->content['out_of_scope'] ?? [];
    }

    public function addOutOfScope(string $item): void
    {
        $this->content['out_of_scope'][] = $item;
    }

    public function problemStatement(): ?string
    {
        return $this->content['problem_statement'] ?? null;
    }

    public function setProblemStatement(string $statement): void
    {
        $this->content['problem_statement'] = $statement;
    }

    public function targetAudience(): ?string
    {
        return $this->content['target_audience'] ?? null;
    }

    public function setTargetAudience(string $audience): void
    {
        $this->content['target_audience'] = $audience;
    }

    public function successCriteria(): array
    {
        return $this->content['success_criteria'] ?? [];
    }

    public function addSuccessCriterion(string $criterion): void
    {
        $this->content['success_criteria'][] = $criterion;
    }

    protected function validateContent(): array
    {
        $errors = [];

        if (empty($this->objectives())) {
            $errors[] = 'At least one objective is required';
        }

        foreach ($this->requirements() as $i => $req) {
            if (empty($req['id'])) {
                $errors[] = "Requirement at index {$i} missing ID";
            }
            if (empty($req['description'])) {
                $errors[] = "Requirement at index {$i} missing description";
            }
        }

        return $errors;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'problem_statement' => $this->problemStatement(),
            'target_audience' => $this->targetAudience(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $doc = new self(
            title: $data['title'] ?? 'Untitled PRD',
            version: $data['version'] ?? '1.0',
            status: $data['status'] ?? 'draft',
            featureId: $data['feature_id'] ?? null,
            content: $data['content'] ?? [],
            path: $data['path'] ?? null,
            metadata: $data['metadata'] ?? [],
        );

        if (isset($data['problem_statement'])) {
            $doc->setProblemStatement($data['problem_statement']);
        }
        if (isset($data['target_audience'])) {
            $doc->setTargetAudience($data['target_audience']);
        }

        return $doc;
    }
}
