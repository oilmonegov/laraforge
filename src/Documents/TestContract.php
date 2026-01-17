<?php

declare(strict_types=1);

namespace LaraForge\Documents;

class TestContract extends Document
{
    public function type(): string
    {
        return 'test-contract';
    }

    /**
     * @return array<array{name: string, type: string, preconditions?: array, action?: array, expectations?: array, invariants?: array}>
     */
    public function contracts(): array
    {
        return $this->content['contracts'] ?? [];
    }

    /**
     * Add a test contract.
     */
    public function addContract(
        string $name,
        string $type = 'feature',
        array $preconditions = [],
        ?array $action = null,
        array $expectations = [],
        array $invariants = [],
    ): void {
        $this->content['contracts'][] = array_filter([
            'name' => $name,
            'type' => $type,
            'preconditions' => $preconditions ?: null,
            'action' => $action,
            'expectations' => $expectations ?: null,
            'invariants' => $invariants ?: null,
        ]);
    }

    /**
     * Add a contract using Given-When-Then format.
     */
    public function addGwtContract(
        string $name,
        string $given,
        string $when,
        string $then,
        string $type = 'feature',
        array $invariants = [],
    ): void {
        $this->content['contracts'][] = array_filter([
            'name' => $name,
            'type' => $type,
            'given' => $given,
            'when' => $when,
            'then' => $then,
            'invariants' => $invariants ?: null,
        ]);
    }

    /**
     * Get contracts by type.
     *
     * @return array<array{name: string, type: string}>
     */
    public function contractsByType(string $type): array
    {
        return array_filter(
            $this->contracts(),
            fn (array $contract) => $contract['type'] === $type
        );
    }

    /**
     * Get feature test contracts.
     *
     * @return array<array{name: string, type: string}>
     */
    public function featureContracts(): array
    {
        return $this->contractsByType('feature');
    }

    /**
     * Get unit test contracts.
     *
     * @return array<array{name: string, type: string}>
     */
    public function unitContracts(): array
    {
        return $this->contractsByType('unit');
    }

    /**
     * Get integration test contracts.
     *
     * @return array<array{name: string, type: string}>
     */
    public function integrationContracts(): array
    {
        return $this->contractsByType('integration');
    }

    /**
     * @return array<string>
     */
    public function globalInvariants(): array
    {
        return $this->content['global_invariants'] ?? [];
    }

    public function addGlobalInvariant(string $invariant): void
    {
        $this->content['global_invariants'][] = $invariant;
    }

    /**
     * @return array<string, mixed>
     */
    public function setupRequirements(): array
    {
        return $this->content['setup_requirements'] ?? [];
    }

    public function setSetupRequirement(string $key, mixed $value): void
    {
        if (! isset($this->content['setup_requirements'])) {
            $this->content['setup_requirements'] = [];
        }
        $this->content['setup_requirements'][$key] = $value;
    }

    public function frdReference(): ?string
    {
        return $this->content['frd_reference'] ?? null;
    }

    public function setFrdReference(string $path): void
    {
        $this->content['frd_reference'] = $path;
    }

    protected function validateContent(): array
    {
        $errors = [];

        if (empty($this->contracts())) {
            $errors[] = 'At least one test contract is required';
        }

        foreach ($this->contracts() as $i => $contract) {
            if (empty($contract['name'])) {
                $errors[] = "Contract at index {$i} missing name";
            }
        }

        return $errors;
    }

    /**
     * Generate a summary of the test contracts.
     *
     * @return array{total: int, by_type: array<string, int>}
     */
    public function summary(): array
    {
        $byType = [];
        foreach ($this->contracts() as $contract) {
            $type = $contract['type'];
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return [
            'total' => count($this->contracts()),
            'by_type' => $byType,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? $data['feature'] ?? 'Untitled Test Contract',
            version: $data['version'] ?? '1.0',
            status: $data['status'] ?? 'draft',
            featureId: $data['feature_id'] ?? null,
            content: array_filter([
                'contracts' => $data['contracts'] ?? $data['content']['contracts'] ?? [],
                'global_invariants' => $data['global_invariants'] ?? $data['content']['global_invariants'] ?? [],
                'setup_requirements' => $data['setup_requirements'] ?? $data['content']['setup_requirements'] ?? [],
                'frd_reference' => $data['frd_reference'] ?? $data['content']['frd_reference'] ?? null,
            ]),
            path: $data['path'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
