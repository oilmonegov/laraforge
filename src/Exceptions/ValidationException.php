<?php

declare(strict_types=1);

namespace LaraForge\Exceptions;

class ValidationException extends LaraForgeException
{
    /** @var array<string, array<string>> */
    private array $errors;

    /**
     * @param  array<string, array<string>>  $errors
     */
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * @return array<string, array<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
