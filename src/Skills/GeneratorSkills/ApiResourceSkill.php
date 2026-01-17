<?php

declare(strict_types=1);

namespace LaraForge\Skills\GeneratorSkills;

use LaraForge\Contracts\GeneratorInterface;
use LaraForge\Generators\ApiResourceGenerator;

class ApiResourceSkill extends GeneratorSkill
{
    public function identifier(): string
    {
        return 'api-resource';
    }

    public function name(): string
    {
        return 'API Resource Generator';
    }

    public function description(): string
    {
        return 'Generates Laravel API Resource classes with optional OpenAPI documentation';
    }

    public function parameters(): array
    {
        return [
            'model' => [
                'type' => 'string',
                'description' => 'The model name (e.g., "User")',
                'required' => true,
            ],
            'attributes' => [
                'type' => 'array',
                'description' => 'Attributes to include in the resource',
                'required' => false,
                'default' => [],
            ],
            'include_collection' => [
                'type' => 'boolean',
                'description' => 'Generate a ResourceCollection class',
                'required' => false,
                'default' => false,
            ],
            'openapi' => [
                'type' => 'boolean',
                'description' => 'Include OpenAPI schema annotations',
                'required' => false,
                'default' => false,
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
        return ['code-generation', 'laravel', 'api', 'resource'];
    }

    protected function createGenerator(): GeneratorInterface
    {
        return new ApiResourceGenerator($this->laraforge);
    }
}
