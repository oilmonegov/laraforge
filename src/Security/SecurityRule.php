<?php

declare(strict_types=1);

namespace LaraForge\Security;

/**
 * Security Rule Definition
 *
 * Represents a single security rule that can be used to validate
 * generated code against security best practices.
 */
final class SecurityRule
{
    /**
     * @param  array<string>  $contexts  Contexts where this rule applies
     */
    public function __construct(
        private readonly string $id,
        private readonly string $pattern,
        private readonly string $message,
        private readonly array $contexts = ['general'],
        private readonly string $severity = 'medium',
        private readonly ?string $recommendation = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string>
     */
    public function contexts(): array
    {
        return $this->contexts;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function recommendation(): ?string
    {
        return $this->recommendation;
    }

    /**
     * Check if this rule applies to the given context.
     */
    public function appliesTo(string $context): bool
    {
        return in_array('general', $this->contexts, true)
            || in_array($context, $this->contexts, true);
    }

    /**
     * Check if the code violates this rule.
     */
    public function isViolated(string $code): bool
    {
        return (bool) preg_match($this->pattern, $code);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pattern' => $this->pattern,
            'message' => $this->message,
            'contexts' => $this->contexts,
            'severity' => $this->severity,
            'recommendation' => $this->recommendation,
        ];
    }
}
