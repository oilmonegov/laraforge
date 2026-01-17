<?php

declare(strict_types=1);

namespace LaraForge\Enums;

/**
 * Implementation style for code generation.
 */
enum ImplementationStyle: string
{
    case Regular = 'regular';
    case TDD = 'tdd';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::TDD => 'Test-Driven Development (TDD)',
        };
    }

    /**
     * Get description for the style.
     */
    public function description(): string
    {
        return match ($this) {
            self::Regular => 'Generate implementation code directly',
            self::TDD => 'Generate test files first, then implementation stubs',
        };
    }

    /**
     * Create from string value with fallback to Regular.
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value)) ?? self::Regular;
    }
}
