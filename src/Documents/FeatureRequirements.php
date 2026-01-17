<?php

declare(strict_types=1);

namespace LaraForge\Documents;

class FeatureRequirements extends Document
{
    public function type(): string
    {
        return 'frd';
    }

    /**
     * @return array<array{id: string, description: string, priority?: string, acceptance_criteria?: array}>
     */
    public function requirements(): array
    {
        return $this->content['requirements'] ?? [];
    }

    public function addRequirement(
        string $id,
        string $description,
        string $priority = 'medium',
        array $acceptanceCriteria = [],
    ): void {
        $this->content['requirements'][] = [
            'id' => $id,
            'description' => $description,
            'priority' => $priority,
            'acceptance_criteria' => $acceptanceCriteria,
        ];
    }

    /**
     * Get stepwise refinement levels.
     *
     * @return array<string, array<string, array<string>>>
     */
    public function stepwiseRefinement(): array
    {
        return $this->content['design']['stepwise_refinement'] ?? [];
    }

    /**
     * Get a specific refinement level.
     *
     * @return array<string, array<string>>|null
     */
    public function refinementLevel(string $level): ?array
    {
        return $this->content['design']['stepwise_refinement'][$level] ?? null;
    }

    /**
     * Add a refinement level.
     *
     * @param  array<string>  $steps
     */
    public function addRefinementLevel(string $level, array $steps): void
    {
        if (! isset($this->content['design'])) {
            $this->content['design'] = [];
        }
        if (! isset($this->content['design']['stepwise_refinement'])) {
            $this->content['design']['stepwise_refinement'] = [];
        }
        $this->content['design']['stepwise_refinement'][$level] = $steps;
    }

    /**
     * Add a refined step under a parent step.
     *
     * @param  array<string>  $substeps
     */
    public function addRefinedStep(string $level, string $stepName, array $substeps): void
    {
        if (! isset($this->content['design']['stepwise_refinement'][$level])) {
            $this->content['design']['stepwise_refinement'][$level] = [];
        }
        $this->content['design']['stepwise_refinement'][$level][$stepName] = $substeps;
    }

    public function pseudocode(): ?string
    {
        return $this->content['design']['pseudocode'] ?? null;
    }

    public function setPseudocode(string $pseudocode): void
    {
        if (! isset($this->content['design'])) {
            $this->content['design'] = [];
        }
        $this->content['design']['pseudocode'] = $pseudocode;
    }

    /**
     * @return array<array{name: string, type: string, scenario?: string, given?: string, when?: string, then?: string, preconditions?: array, expectations?: array, invariants?: array}>
     */
    public function testContracts(): array
    {
        return $this->content['test_contract'] ?? [];
    }

    public function addTestContract(
        string $name,
        string $type = 'feature',
        ?string $scenario = null,
        ?string $given = null,
        ?string $when = null,
        ?string $then = null,
        array $preconditions = [],
        array $expectations = [],
        array $invariants = [],
    ): void {
        $this->content['test_contract'][] = array_filter([
            'name' => $name,
            'type' => $type,
            'scenario' => $scenario,
            'given' => $given,
            'when' => $when,
            'then' => $then,
            'preconditions' => $preconditions ?: null,
            'expectations' => $expectations ?: null,
            'invariants' => $invariants ?: null,
        ]);
    }

    /**
     * @return array<string>
     */
    public function dependencies(): array
    {
        return $this->content['dependencies'] ?? [];
    }

    public function addDependency(string $dependency): void
    {
        $this->content['dependencies'][] = $dependency;
    }

    /**
     * @return array<string, mixed>
     */
    public function technicalNotes(): array
    {
        return $this->content['technical_notes'] ?? [];
    }

    public function setTechnicalNote(string $key, mixed $value): void
    {
        if (! isset($this->content['technical_notes'])) {
            $this->content['technical_notes'] = [];
        }
        $this->content['technical_notes'][$key] = $value;
    }

    public function prdReference(): ?string
    {
        return $this->content['prd_reference'] ?? null;
    }

    public function setPrdReference(string $path): void
    {
        $this->content['prd_reference'] = $path;
    }

    protected function validateContent(): array
    {
        $errors = [];

        if (empty($this->requirements())) {
            $errors[] = 'At least one requirement is required';
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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? $data['feature'] ?? 'Untitled FRD',
            version: $data['version'] ?? '1.0',
            status: $data['status'] ?? 'draft',
            featureId: $data['feature_id'] ?? null,
            content: array_filter([
                'requirements' => $data['requirements'] ?? $data['content']['requirements'] ?? [],
                'design' => $data['design'] ?? $data['content']['design'] ?? [],
                'test_contract' => $data['test_contract'] ?? $data['content']['test_contract'] ?? [],
                'dependencies' => $data['dependencies'] ?? $data['content']['dependencies'] ?? [],
                'technical_notes' => $data['technical_notes'] ?? $data['content']['technical_notes'] ?? [],
                'prd_reference' => $data['prd_reference'] ?? $data['content']['prd_reference'] ?? null,
            ]),
            path: $data['path'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
