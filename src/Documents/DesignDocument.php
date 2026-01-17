<?php

declare(strict_types=1);

namespace LaraForge\Documents;

class DesignDocument extends Document
{
    public function type(): string
    {
        return 'design';
    }

    public function overview(): ?string
    {
        return $this->content['overview'] ?? null;
    }

    public function setOverview(string $overview): void
    {
        $this->content['overview'] = $overview;
    }

    /**
     * @return array<array{name: string, purpose: string, interfaces?: array, dependencies?: array}>
     */
    public function components(): array
    {
        return $this->content['components'] ?? [];
    }

    public function addComponent(
        string $name,
        string $purpose,
        array $interfaces = [],
        array $dependencies = [],
    ): void {
        $this->content['components'][] = array_filter([
            'name' => $name,
            'purpose' => $purpose,
            'interfaces' => $interfaces ?: null,
            'dependencies' => $dependencies ?: null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function pseudocode(): array
    {
        return $this->content['pseudocode'] ?? [];
    }

    public function setPseudocodeFor(string $component, string $pseudocode): void
    {
        if (! isset($this->content['pseudocode'])) {
            $this->content['pseudocode'] = [];
        }
        $this->content['pseudocode'][$component] = $pseudocode;
    }

    public function getPseudocodeFor(string $component): ?string
    {
        return $this->content['pseudocode'][$component] ?? null;
    }

    /**
     * @return array<array{from: string, to: string, description?: string}>
     */
    public function dataFlows(): array
    {
        return $this->content['data_flows'] ?? [];
    }

    public function addDataFlow(string $from, string $to, ?string $description = null): void
    {
        $this->content['data_flows'][] = array_filter([
            'from' => $from,
            'to' => $to,
            'description' => $description,
        ]);
    }

    /**
     * @return array<array{id: string, description: string, options?: array, decision?: string, rationale?: string}>
     */
    public function architecturalDecisions(): array
    {
        return $this->content['architectural_decisions'] ?? [];
    }

    public function addArchitecturalDecision(
        string $id,
        string $description,
        array $options = [],
        ?string $decision = null,
        ?string $rationale = null,
    ): void {
        $this->content['architectural_decisions'][] = array_filter([
            'id' => $id,
            'description' => $description,
            'options' => $options ?: null,
            'decision' => $decision,
            'rationale' => $rationale,
        ]);
    }

    /**
     * @return array<string, array{path: string, purpose: string, creates?: array, modifies?: array}>
     */
    public function fileStructure(): array
    {
        return $this->content['file_structure'] ?? [];
    }

    public function addFileToStructure(
        string $name,
        string $path,
        string $purpose,
        array $creates = [],
        array $modifies = [],
    ): void {
        $this->content['file_structure'][$name] = array_filter([
            'path' => $path,
            'purpose' => $purpose,
            'creates' => $creates ?: null,
            'modifies' => $modifies ?: null,
        ]);
    }

    /**
     * @return array<string>
     */
    public function interfaces(): array
    {
        return $this->content['interfaces'] ?? [];
    }

    public function addInterface(string $interface): void
    {
        $this->content['interfaces'][] = $interface;
    }

    public function setInterfaceDefinition(string $name, string $definition): void
    {
        if (! isset($this->content['interface_definitions'])) {
            $this->content['interface_definitions'] = [];
        }
        $this->content['interface_definitions'][$name] = $definition;
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

        if (empty($this->overview()) && empty($this->components())) {
            $errors[] = 'Design document must have an overview or components';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? 'Untitled Design',
            version: $data['version'] ?? '1.0',
            status: $data['status'] ?? 'draft',
            featureId: $data['feature_id'] ?? null,
            content: $data['content'] ?? [],
            path: $data['path'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
