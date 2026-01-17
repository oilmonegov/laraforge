<?php

declare(strict_types=1);

use LaraForge\Hooks\Hook;
use LaraForge\Hooks\HookRegistry;
use LaraForge\Project\ProjectContext;
use LaraForge\Project\ProjectState;
use Symfony\Component\Filesystem\Filesystem;

class HookRegistryTestHook extends Hook
{
    public function __construct(
        private readonly string $id,
        private readonly string $hookName,
        private readonly string $hookType,
        private readonly int $hookPriority = 100,
        private readonly bool $shouldBlock = false,
        private readonly array $outputData = [],
        private readonly bool $runs = true,
    ) {}

    public function identifier(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->hookName;
    }

    public function type(): string
    {
        return $this->hookType;
    }

    public function priority(): int
    {
        return $this->hookPriority;
    }

    public function shouldRun(ProjectContext $context, array $eventData = []): bool
    {
        return $this->runs;
    }

    public function execute(ProjectContext $context, array $eventData = []): array
    {
        if ($this->shouldBlock) {
            return $this->block('Hook blocked execution');
        }

        return $this->continue($this->outputData);
    }
}

describe('HookRegistry', function () {
    beforeEach(function () {
        $this->tempDir = createTempDirectory();
        $this->filesystem = new Filesystem;
        $this->laraforge = laraforge($this->tempDir);
        $this->state = ProjectState::initialize($this->tempDir, 'Test');
        $this->context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $this->state,
        );
        $this->registry = new HookRegistry;
    });

    afterEach(function () {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    });

    it('registers a hook', function () {
        $hook = new HookRegistryTestHook('test-hook', 'Test Hook', 'pre-workflow');

        $this->registry->register($hook);

        expect($this->registry->get('test-hook'))->toBe($hook)
            ->and($this->registry->count())->toBe(1);
    });

    it('retrieves hooks by type', function () {
        $this->registry->register(new HookRegistryTestHook('pre-1', 'Pre 1', 'pre-workflow'));
        $this->registry->register(new HookRegistryTestHook('pre-2', 'Pre 2', 'pre-workflow'));
        $this->registry->register(new HookRegistryTestHook('post-1', 'Post 1', 'post-workflow'));

        $preHooks = $this->registry->byType('pre-workflow');
        $postHooks = $this->registry->byType('post-workflow');
        $validationHooks = $this->registry->byType('validation');

        expect($preHooks)->toHaveCount(2)
            ->and($postHooks)->toHaveCount(1)
            ->and($validationHooks)->toHaveCount(0);
    });

    it('returns all hooks', function () {
        $this->registry->register(new HookRegistryTestHook('pre-1', 'Pre 1', 'pre-workflow'));
        $this->registry->register(new HookRegistryTestHook('post-1', 'Post 1', 'post-workflow'));

        $all = $this->registry->all();

        expect($all)->toHaveKey('pre-workflow')
            ->and($all)->toHaveKey('post-workflow')
            ->and($all['pre-workflow'])->toHaveCount(1)
            ->and($all['post-workflow'])->toHaveCount(1);
    });

    it('sorts hooks by priority', function () {
        $this->registry->register(new HookRegistryTestHook('low', 'Low', 'pre-workflow', 200));
        $this->registry->register(new HookRegistryTestHook('high', 'High', 'pre-workflow', 50));
        $this->registry->register(new HookRegistryTestHook('medium', 'Medium', 'pre-workflow', 100));

        $hooks = $this->registry->byType('pre-workflow');

        expect($hooks[0]->identifier())->toBe('high')
            ->and($hooks[1]->identifier())->toBe('medium')
            ->and($hooks[2]->identifier())->toBe('low');
    });

    it('executes hooks successfully', function () {
        $this->registry->register(new HookRegistryTestHook('hook-1', 'Hook 1', 'pre-workflow', 100, false, ['key1' => 'value1']));
        $this->registry->register(new HookRegistryTestHook('hook-2', 'Hook 2', 'pre-workflow', 200, false, ['key2' => 'value2']));

        $result = $this->registry->execute('pre-workflow', $this->context);

        expect($result['success'])->toBeTrue()
            ->and($result['results'])->toHaveKey('hook-1')
            ->and($result['results'])->toHaveKey('hook-2')
            ->and($result['data'])->toHaveKey('key1')
            ->and($result['data'])->toHaveKey('key2');
    });

    it('stops execution when hook blocks', function () {
        $this->registry->register(new HookRegistryTestHook('passing', 'Passing', 'pre-workflow', 50));
        $this->registry->register(new HookRegistryTestHook('blocking', 'Blocking', 'pre-workflow', 100, true));
        $this->registry->register(new HookRegistryTestHook('never-runs', 'Never Runs', 'pre-workflow', 200));

        $result = $this->registry->execute('pre-workflow', $this->context);

        expect($result['success'])->toBeFalse()
            ->and($result['results'])->toHaveKey('passing')
            ->and($result['results'])->toHaveKey('blocking')
            ->and($result['results'])->not->toHaveKey('never-runs')
            ->and($result)->toHaveKey('error')
            ->and($result)->toHaveKey('blocked_by')
            ->and($result['blocked_by'])->toBe('blocking');
    });

    it('skips hooks that should not run', function () {
        $this->registry->register(new HookRegistryTestHook('runs', 'Runs', 'pre-workflow', 100, false, [], true));
        $this->registry->register(new HookRegistryTestHook('skipped', 'Skipped', 'pre-workflow', 200, false, [], false));

        $result = $this->registry->execute('pre-workflow', $this->context);

        expect($result['success'])->toBeTrue()
            ->and($result['results'])->toHaveKey('runs')
            ->and($result['results'])->not->toHaveKey('skipped');
    });

    it('passes data between hooks', function () {
        $this->registry->register(new HookRegistryTestHook('first', 'First', 'pre-workflow', 100, false, ['from_first' => 'value']));
        $this->registry->register(new HookRegistryTestHook('second', 'Second', 'pre-workflow', 200, false, ['from_second' => 'value']));

        $result = $this->registry->execute('pre-workflow', $this->context, ['initial' => 'data']);

        expect($result['data'])->toHaveKey('initial')
            ->and($result['data'])->toHaveKey('from_first')
            ->and($result['data'])->toHaveKey('from_second');
    });

    it('provides convenience methods for hook types', function () {
        $this->registry->register(new HookRegistryTestHook('pre', 'Pre', 'pre-workflow'));
        $this->registry->register(new HookRegistryTestHook('post', 'Post', 'post-workflow'));
        $this->registry->register(new HookRegistryTestHook('pre-step', 'Pre Step', 'pre-step'));
        $this->registry->register(new HookRegistryTestHook('post-step', 'Post Step', 'post-step'));
        $this->registry->register(new HookRegistryTestHook('validation', 'Validation', 'validation'));

        $preResult = $this->registry->executePreWorkflow($this->context);
        $postResult = $this->registry->executePostWorkflow($this->context);
        $preStepResult = $this->registry->executePreStep($this->context);
        $postStepResult = $this->registry->executePostStep($this->context);
        $validationResult = $this->registry->executeValidation($this->context);

        expect($preResult['results'])->toHaveKey('pre')
            ->and($postResult['results'])->toHaveKey('post')
            ->and($preStepResult['results'])->toHaveKey('pre-step')
            ->and($postStepResult['results'])->toHaveKey('post-step')
            ->and($validationResult['results'])->toHaveKey('validation');
    });

    it('removes hooks by identifier', function () {
        $this->registry->register(new HookRegistryTestHook('to-remove', 'Remove', 'pre-workflow'));
        $this->registry->register(new HookRegistryTestHook('to-keep', 'Keep', 'pre-workflow'));

        expect($this->registry->count())->toBe(2);

        $this->registry->remove('to-remove');

        expect($this->registry->count())->toBe(1)
            ->and($this->registry->get('to-remove'))->toBeNull()
            ->and($this->registry->get('to-keep'))->not->toBeNull();
    });

    it('returns available hook types', function () {
        $this->registry->register(new HookRegistryTestHook('pre', 'Pre', 'pre-workflow'));
        $this->registry->register(new HookRegistryTestHook('post', 'Post', 'post-workflow'));
        $this->registry->register(new HookRegistryTestHook('val', 'Val', 'validation'));

        $types = $this->registry->types();

        expect($types)->toContain('pre-workflow')
            ->and($types)->toContain('post-workflow')
            ->and($types)->toContain('validation')
            ->and($types)->toHaveCount(3);
    });

    it('returns hook metadata', function () {
        $this->registry->register(new HookRegistryTestHook('test-hook', 'Test Hook', 'pre-workflow', 50));

        $metadata = $this->registry->metadata();

        expect($metadata)->toHaveKey('test-hook')
            ->and($metadata['test-hook'])->toHaveKey('identifier')
            ->and($metadata['test-hook']['identifier'])->toBe('test-hook')
            ->and($metadata['test-hook'])->toHaveKey('name')
            ->and($metadata['test-hook']['name'])->toBe('Test Hook')
            ->and($metadata['test-hook'])->toHaveKey('type')
            ->and($metadata['test-hook']['type'])->toBe('pre-workflow')
            ->and($metadata['test-hook'])->toHaveKey('priority')
            ->and($metadata['test-hook']['priority'])->toBe(50)
            ->and($metadata['test-hook'])->toHaveKey('skippable');
    });

    it('counts all registered hooks', function () {
        expect($this->registry->count())->toBe(0);

        $this->registry->register(new HookRegistryTestHook('pre-1', 'Pre 1', 'pre-workflow'));
        $this->registry->register(new HookRegistryTestHook('pre-2', 'Pre 2', 'pre-workflow'));
        $this->registry->register(new HookRegistryTestHook('post-1', 'Post 1', 'post-workflow'));

        expect($this->registry->count())->toBe(3);
    });

    it('returns null for non-existent hook', function () {
        expect($this->registry->get('non-existent'))->toBeNull();
    });
});
