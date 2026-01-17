<?php

declare(strict_types=1);

namespace LaraForge\Skills;

use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillInterface;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Contracts\ValidationResultInterface;

abstract class Skill implements SkillInterface
{
    protected ?LaraForgeInterface $laraforge = null;

    public function setLaraForge(LaraForgeInterface $laraforge): void
    {
        $this->laraforge = $laraforge;
    }

    abstract public function identifier(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    abstract public function parameters(): array;

    abstract protected function perform(array $params, ProjectContext $context): SkillResultInterface;

    public function execute(array $params): SkillResultInterface
    {
        $context = $this->createContext();

        $validation = $this->validate($params);
        if (! $validation->isValid()) {
            return SkillResult::failure(
                'Validation failed: '.implode(', ', $validation->allErrors()),
                metadata: ['validation_errors' => $validation->errors()]
            );
        }

        if (! $this->canExecute($context)) {
            return SkillResult::failure(
                'Skill cannot be executed in the current project context'
            );
        }

        return $this->perform($params, $context);
    }

    public function validate(array $params): ValidationResultInterface
    {
        $errors = [];
        $warnings = [];

        foreach ($this->parameters() as $name => $definition) {
            $required = $definition['required'] ?? false;
            $type = $definition['type'];

            if ($required && ! isset($params[$name])) {
                $errors[$name] = ["The {$name} parameter is required"];

                continue;
            }

            if (isset($params[$name])) {
                $value = $params[$name];
                $typeError = $this->validateType($value, $type);
                if ($typeError) {
                    $errors[$name] = [$typeError];
                }
            }
        }

        // Check for unknown parameters
        $knownParams = array_keys($this->parameters());
        $unknownParams = array_diff(array_keys($params), $knownParams);
        foreach ($unknownParams as $unknown) {
            $warnings[$unknown] = ['Unknown parameter'];
        }

        return new ValidationResult(errors: $errors, warnings: $warnings);
    }

    public function canExecute(ProjectContext $context): bool
    {
        return true;
    }

    public function category(): string
    {
        return 'general';
    }

    public function tags(): array
    {
        return [];
    }

    protected function createContext(): ProjectContext
    {
        if (! $this->laraforge) {
            throw new \RuntimeException('LaraForge instance not set on skill');
        }

        return new ProjectContext($this->laraforge);
    }

    protected function validateType(mixed $value, string $type): ?string
    {
        return match ($type) {
            'string' => is_string($value) ? null : 'Must be a string',
            'int', 'integer' => is_int($value) ? null : 'Must be an integer',
            'float', 'number' => is_numeric($value) ? null : 'Must be a number',
            'bool', 'boolean' => is_bool($value) ? null : 'Must be a boolean',
            'array' => is_array($value) ? null : 'Must be an array',
            'object' => is_object($value) || is_array($value) ? null : 'Must be an object',
            default => null,
        };
    }

    protected function getParam(array $params, string $key, mixed $default = null): mixed
    {
        if (isset($params[$key])) {
            return $params[$key];
        }

        $definition = $this->parameters()[$key] ?? null;
        if ($definition && isset($definition['default'])) {
            return $definition['default'];
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveParams(array $params): array
    {
        $resolved = [];

        foreach ($this->parameters() as $name => $definition) {
            $resolved[$name] = $this->getParam($params, $name);
        }

        return $resolved;
    }
}
