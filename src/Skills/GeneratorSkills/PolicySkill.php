<?php

declare(strict_types=1);

namespace LaraForge\Skills\GeneratorSkills;

use LaraForge\Contracts\GeneratorInterface;
use LaraForge\Generators\PolicyGenerator;

class PolicySkill extends GeneratorSkill
{
    public function identifier(): string
    {
        return 'policy';
    }

    public function name(): string
    {
        return 'Policy Generator';
    }

    public function description(): string
    {
        return 'Generates Laravel Policy classes for authorization';
    }

    public function parameters(): array
    {
        return [
            'model' => [
                'type' => 'string',
                'description' => 'The model name',
                'required' => true,
            ],
            'abilities' => [
                'type' => 'array',
                'description' => 'Abilities to include (viewAny, view, create, update, delete, restore, forceDelete)',
                'required' => false,
                'default' => ['viewAny', 'view', 'create', 'update', 'delete'],
            ],
            'user_model' => [
                'type' => 'string',
                'description' => 'The user model class',
                'required' => false,
                'default' => 'User',
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
        return ['code-generation', 'laravel', 'authorization', 'policy'];
    }

    protected function createGenerator(): GeneratorInterface
    {
        return new PolicyGenerator($this->laraforge);
    }
}
