<?php

declare(strict_types=1);

namespace LaraForge\Hooks;

use LaraForge\Hooks\Contracts\HookInterface;
use LaraForge\Project\ProjectContext;

class HookRegistry
{
    /**
     * @var array<string, array<HookInterface>>
     */
    private array $hooks = [];

    public function register(HookInterface $hook): void
    {
        $type = $hook->type();

        if (! isset($this->hooks[$type])) {
            $this->hooks[$type] = [];
        }

        $this->hooks[$type][] = $hook;

        // Sort by priority (lower runs first)
        usort(
            $this->hooks[$type],
            fn (HookInterface $a, HookInterface $b) => $a->priority() <=> $b->priority()
        );
    }

    public function get(string $identifier): ?HookInterface
    {
        foreach ($this->hooks as $hooks) {
            foreach ($hooks as $hook) {
                if ($hook->identifier() === $identifier) {
                    return $hook;
                }
            }
        }

        return null;
    }

    /**
     * Get all hooks of a specific type.
     *
     * @return array<HookInterface>
     */
    public function byType(string $type): array
    {
        return $this->hooks[$type] ?? [];
    }

    /**
     * Get all registered hooks.
     *
     * @return array<string, array<HookInterface>>
     */
    public function all(): array
    {
        return $this->hooks;
    }

    /**
     * Execute all hooks of a given type.
     *
     * @return array{success: bool, results: array, error?: string}
     */
    public function execute(string $type, ProjectContext $context, array $eventData = []): array
    {
        $hooks = $this->byType($type);
        $results = [];

        foreach ($hooks as $hook) {
            if (! $hook->shouldRun($context, $eventData)) {
                continue;
            }

            $result = $hook->execute($context, $eventData);
            $results[$hook->identifier()] = $result;

            if (! $result['continue']) {
                return [
                    'success' => false,
                    'results' => $results,
                    'error' => $result['error'] ?? 'Hook blocked execution',
                    'blocked_by' => $hook->identifier(),
                ];
            }

            // Merge data from hook result into event data for next hooks
            if (isset($result['data'])) {
                $eventData = array_merge($eventData, $result['data']);
            }
        }

        return [
            'success' => true,
            'results' => $results,
            'data' => $eventData,
        ];
    }

    /**
     * Execute pre-workflow hooks.
     */
    public function executePreWorkflow(ProjectContext $context, array $eventData = []): array
    {
        return $this->execute('pre-workflow', $context, $eventData);
    }

    /**
     * Execute post-workflow hooks.
     */
    public function executePostWorkflow(ProjectContext $context, array $eventData = []): array
    {
        return $this->execute('post-workflow', $context, $eventData);
    }

    /**
     * Execute pre-step hooks.
     */
    public function executePreStep(ProjectContext $context, array $eventData = []): array
    {
        return $this->execute('pre-step', $context, $eventData);
    }

    /**
     * Execute post-step hooks.
     */
    public function executePostStep(ProjectContext $context, array $eventData = []): array
    {
        return $this->execute('post-step', $context, $eventData);
    }

    /**
     * Execute validation hooks.
     */
    public function executeValidation(ProjectContext $context, array $eventData = []): array
    {
        return $this->execute('validation', $context, $eventData);
    }

    /**
     * Remove a hook by identifier.
     */
    public function remove(string $identifier): void
    {
        foreach ($this->hooks as $type => &$hooks) {
            $hooks = array_filter(
                $hooks,
                fn (HookInterface $h) => $h->identifier() !== $identifier
            );
        }
    }

    /**
     * Get all available hook types.
     *
     * @return array<string>
     */
    public function types(): array
    {
        return array_keys($this->hooks);
    }

    /**
     * Get hook metadata for all registered hooks.
     *
     * @return array<string, array{identifier: string, name: string, type: string, priority: int, skippable: bool}>
     */
    public function metadata(): array
    {
        $metadata = [];

        foreach ($this->hooks as $type => $hooks) {
            foreach ($hooks as $hook) {
                $metadata[$hook->identifier()] = [
                    'identifier' => $hook->identifier(),
                    'name' => $hook->name(),
                    'type' => $hook->type(),
                    'priority' => $hook->priority(),
                    'skippable' => $hook->isSkippable(),
                    'metadata' => $hook->metadata(),
                ];
            }
        }

        return $metadata;
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->hooks as $hooks) {
            $count += count($hooks);
        }

        return $count;
    }
}
