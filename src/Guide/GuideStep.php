<?php

declare(strict_types=1);

namespace LaraForge\Guide;

class GuideStep
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $command,
        public readonly string $phase,
        public readonly bool $required,
        public readonly int $order,
        public readonly ?string $prerequisite = null,
        public readonly ?string $alternativeCommand = null,
        public readonly bool $manualStep = false,
    ) {}

    /**
     * Get a formatted display of the step.
     */
    public function display(): string
    {
        $type = $this->required ? '[Required]' : '[Optional]';
        $phase = ucfirst($this->phase);

        return "{$type} {$this->name} ({$phase})";
    }

    /**
     * Check if this step can be skipped.
     */
    public function canSkip(): bool
    {
        return ! $this->required;
    }

    /**
     * Check if this step has a command to run.
     */
    public function hasCommand(): bool
    {
        return $this->command !== null && ! $this->manualStep;
    }

    /**
     * Check if this step has an alternative command.
     */
    public function hasAlternative(): bool
    {
        return $this->alternativeCommand !== null;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'command' => $this->command,
            'alternative_command' => $this->alternativeCommand,
            'phase' => $this->phase,
            'required' => $this->required,
            'order' => $this->order,
            'prerequisite' => $this->prerequisite,
            'manual_step' => $this->manualStep,
        ];
    }
}
