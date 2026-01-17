<?php

declare(strict_types=1);

namespace LaraForge\Criteria;

/**
 * Represents a single acceptance criterion.
 */
final readonly class AcceptanceCriterion
{
    /**
     * @param  array<string>  $assertions
     */
    public function __construct(
        public string $id,
        public string $description,
        public array $assertions = [],
    ) {}

    /**
     * Create from array data.
     *
     * @param  array{id: string, description: string, assertions?: array<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            description: $data['description'],
            assertions: $data['assertions'] ?? [],
        );
    }

    /**
     * Convert to array.
     *
     * @return array{id: string, description: string, assertions: array<string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'assertions' => $this->assertions,
        ];
    }

    /**
     * Generate a test method name from the description.
     */
    public function toTestMethodName(): string
    {
        $name = strtolower($this->description);
        $name = (string) preg_replace('/[^a-z0-9\s]/', '', $name);
        $name = (string) preg_replace('/\s+/', '_', trim($name));

        return $name;
    }

    /**
     * Generate a human-readable test name (for Pest's "it" function).
     */
    public function toTestLabel(): string
    {
        $label = strtolower($this->description);
        $label = trim($label);

        return $label;
    }
}
