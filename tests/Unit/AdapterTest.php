<?php

declare(strict_types=1);

use LaraForge\Adapters\Adapter;
use LaraForge\Contracts\AdapterInterface;
use LaraForge\LaraForge;

class TestAdapter extends Adapter
{
    public function identifier(): string
    {
        return 'test-adapter';
    }

    public function name(): string
    {
        return 'Test Adapter';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function isApplicable(string $projectPath): bool
    {
        return file_exists($projectPath . '/test-marker.txt');
    }

    public function templatesPath(): string
    {
        return __DIR__ . '/fixtures/templates';
    }

    public function stubsPath(): string
    {
        return __DIR__ . '/fixtures/stubs';
    }
}

class HighPriorityAdapter extends TestAdapter
{
    public function identifier(): string
    {
        return 'high-priority';
    }

    public function priority(): int
    {
        return 100;
    }
}

describe('Adapter', function () {
    it('can register an adapter', function () {
        $laraforge = laraforge();
        $adapter = new TestAdapter();

        $laraforge->registerAdapter($adapter);

        expect($laraforge->adapters())->toHaveKey('test-adapter');
    });

    it('returns null adapter when none applicable', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $laraforge->registerAdapter(new TestAdapter());

        expect($laraforge->adapter())->toBeNull();
    });

    it('returns applicable adapter', function () {
        $tempDir = createTempDirectory();
        file_put_contents($tempDir . '/test-marker.txt', '');

        $laraforge = new LaraForge($tempDir);
        $laraforge->registerAdapter(new TestAdapter());

        expect($laraforge->adapter())
            ->toBeInstanceOf(AdapterInterface::class)
            ->and($laraforge->adapter()->identifier())->toBe('test-adapter');
    });

    it('selects highest priority adapter when multiple apply', function () {
        $tempDir = createTempDirectory();
        file_put_contents($tempDir . '/test-marker.txt', '');

        $laraforge = new LaraForge($tempDir);
        $laraforge->registerAdapter(new TestAdapter()); // priority 10
        $laraforge->registerAdapter(new HighPriorityAdapter()); // priority 100

        expect($laraforge->adapter()->identifier())->toBe('high-priority');
    });

    it('caches active adapter', function () {
        $tempDir = createTempDirectory();
        file_put_contents($tempDir . '/test-marker.txt', '');

        $laraforge = new LaraForge($tempDir);
        $laraforge->registerAdapter(new TestAdapter());

        $adapter1 = $laraforge->adapter();
        $adapter2 = $laraforge->adapter();

        expect($adapter1)->toBe($adapter2);
    });

    it('resets cached adapter when working directory changes', function () {
        $tempDir1 = createTempDirectory();
        $tempDir2 = createTempDirectory();
        file_put_contents($tempDir1 . '/test-marker.txt', '');

        $laraforge = new LaraForge($tempDir1);
        $laraforge->registerAdapter(new TestAdapter());

        expect($laraforge->adapter())->not->toBeNull();

        $laraforge->setWorkingDirectory($tempDir2);

        expect($laraforge->adapter())->toBeNull();
    });
});
