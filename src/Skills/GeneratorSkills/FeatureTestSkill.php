<?php

declare(strict_types=1);

namespace LaraForge\Skills\GeneratorSkills;

use LaraForge\Contracts\GeneratorInterface;
use LaraForge\Generators\FeatureTestGenerator;

class FeatureTestSkill extends GeneratorSkill
{
    public function identifier(): string
    {
        return 'feature-test';
    }

    public function name(): string
    {
        return 'Feature Test Generator';
    }

    public function description(): string
    {
        return 'Generates Pest feature tests from acceptance criteria';
    }

    public function parameters(): array
    {
        return [
            'feature' => [
                'type' => 'string',
                'description' => 'Feature name',
                'required' => true,
            ],
            'criteria_file' => [
                'type' => 'string',
                'description' => 'Path to criteria YAML/JSON file',
                'required' => false,
            ],
            'test_type' => [
                'type' => 'string',
                'description' => 'Test type: "feature" or "unit"',
                'required' => false,
                'default' => 'feature',
            ],
            'http_methods' => [
                'type' => 'array',
                'description' => 'HTTP methods to scaffold',
                'required' => false,
                'default' => [],
            ],
            'resource' => [
                'type' => 'string',
                'description' => 'Resource name for endpoints',
                'required' => false,
            ],
        ];
    }

    public function tags(): array
    {
        return ['code-generation', 'laravel', 'testing', 'pest', 'feature-test'];
    }

    protected function createGenerator(): GeneratorInterface
    {
        return new FeatureTestGenerator($this->laraforge);
    }
}
