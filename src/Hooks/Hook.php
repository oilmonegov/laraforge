<?php

declare(strict_types=1);

namespace LaraForge\Hooks;

use LaraForge\Hooks\Contracts\HookInterface;
use LaraForge\Project\ProjectContext;

abstract class Hook implements HookInterface
{
    abstract public function identifier(): string;

    abstract public function name(): string;

    abstract public function type(): string;

    public function priority(): int
    {
        return 100;
    }

    public function shouldRun(ProjectContext $context, array $eventData = []): bool
    {
        return true;
    }

    abstract public function execute(ProjectContext $context, array $eventData = []): array;

    public function isSkippable(): bool
    {
        return true;
    }

    public function metadata(): array
    {
        return [];
    }

    /**
     * Helper to return a successful hook result.
     *
     * @param  array<string, mixed>  $data
     * @return array{continue: bool, data?: array}
     */
    protected function continue(array $data = []): array
    {
        $result = ['continue' => true];
        if (! empty($data)) {
            $result['data'] = $data;
        }

        return $result;
    }

    /**
     * Helper to return a blocking hook result.
     *
     * @return array{continue: bool, error: string}
     */
    protected function block(string $error): array
    {
        return [
            'continue' => false,
            'error' => $error,
        ];
    }
}
