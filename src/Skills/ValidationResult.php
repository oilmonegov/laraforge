<?php

declare(strict_types=1);

namespace LaraForge\Skills;

use LaraForge\Skills\Contracts\ValidationResultInterface;

final class ValidationResult implements ValidationResultInterface
{
    /**
     * @param  array<string, array<string>>  $errors
     * @param  array<string, array<string>>  $warnings
     */
    public function __construct(
        private readonly array $errors = [],
        private readonly array $warnings = [],
    ) {}

    public static function valid(): self
    {
        return new self;
    }

    public static function invalid(array $errors, array $warnings = []): self
    {
        return new self(errors: $errors, warnings: $warnings);
    }

    public static function withWarnings(array $warnings): self
    {
        return new self(warnings: $warnings);
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function allErrors(): array
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }

        return $messages;
    }

    public function allWarnings(): array
    {
        $messages = [];
        foreach ($this->warnings as $field => $fieldWarnings) {
            foreach ($fieldWarnings as $warning) {
                $messages[] = "{$field}: {$warning}";
            }
        }

        return $messages;
    }

    public function merge(ValidationResultInterface $other): self
    {
        $errors = $this->errors;
        $warnings = $this->warnings;

        foreach ($other->errors() as $field => $fieldErrors) {
            $errors[$field] = array_merge($errors[$field] ?? [], $fieldErrors);
        }

        foreach ($other->warnings() as $field => $fieldWarnings) {
            $warnings[$field] = array_merge($warnings[$field] ?? [], $fieldWarnings);
        }

        return new self(errors: $errors, warnings: $warnings);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
