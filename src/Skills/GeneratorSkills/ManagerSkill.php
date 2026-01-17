<?php

declare(strict_types=1);

namespace LaraForge\Skills\GeneratorSkills;

use LaraForge\Contracts\GeneratorInterface;
use LaraForge\Generators\ManagerGenerator;

class ManagerSkill extends GeneratorSkill
{
    public function identifier(): string
    {
        return 'manager';
    }

    public function name(): string
    {
        return 'Manager Pattern Generator';
    }

    public function description(): string
    {
        return 'Generates Laravel Manager pattern with pluggable drivers and optional Saloon integration';
    }

    public function parameters(): array
    {
        return [
            'service' => [
                'type' => 'string',
                'description' => 'Service name (e.g., "Payment")',
                'required' => true,
            ],
            'drivers' => [
                'type' => 'array',
                'description' => 'List of driver names',
                'required' => false,
                'default' => [],
            ],
            'methods' => [
                'type' => 'array',
                'description' => 'List of methods for interface',
                'required' => false,
                'default' => ['all', 'find', 'create', 'update', 'delete'],
            ],
            'use_saloon' => [
                'type' => 'boolean',
                'description' => 'Generate Saloon HTTP client integration',
                'required' => false,
                'default' => false,
            ],
            'include_config' => [
                'type' => 'boolean',
                'description' => 'Generate config file',
                'required' => false,
                'default' => true,
            ],
            'include_provider' => [
                'type' => 'boolean',
                'description' => 'Generate service provider',
                'required' => false,
                'default' => true,
            ],
            'style' => [
                'type' => 'string',
                'description' => 'Implementation style: "regular" or "tdd"',
                'required' => false,
                'default' => 'regular',
            ],
        ];
    }

    public function tags(): array
    {
        return ['code-generation', 'laravel', 'manager', 'driver-pattern', 'service'];
    }

    protected function createGenerator(): GeneratorInterface
    {
        return new ManagerGenerator($this->laraforge);
    }
}
