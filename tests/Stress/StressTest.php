<?php

declare(strict_types=1);

/**
 * Stress Tests for LaraForge
 *
 * These tests verify performance and stability under load.
 * They use Pest's stressless plugin for non-blocking stress testing.
 *
 * Run with: pest --filter=Stress
 *
 * Note: These tests are optional and may require additional setup.
 * Configure base URL in phpunit.xml or .env for HTTP stress tests.
 */

use function Pest\Stressless\stress;

describe('Stress Tests', function () {
    /**
     * Example stress test for an HTTP endpoint.
     * Uncomment and configure when you have an API to test.
     */
    it('handles concurrent requests to homepage', function () {
        $result = stress('https://example.com')
            ->concurrently(10)
            ->for(5)->seconds();

        expect($result->requests()->successful()->count())
            ->toBeGreaterThan(0);
    })->skip('Configure base URL for stress testing');

    /**
     * Example: Test API endpoint under load
     */
    it('handles concurrent API requests', function () {
        $result = stress('https://api.example.com/v1/health')
            ->concurrently(50)
            ->for(10)->seconds();

        expect($result->requests()->failed()->count())
            ->toBe(0)
            ->and($result->requests()->duration()->average())
            ->toBeLessThan(500); // Less than 500ms average
    })->skip('Configure API URL for stress testing');

    /**
     * Example: Test with POST data
     */
    it('handles concurrent POST requests', function () {
        $result = stress('https://api.example.com/v1/items')
            ->post(['name' => 'Test Item'])
            ->withHeaders(['Authorization' => 'Bearer token'])
            ->concurrently(20)
            ->for(5)->seconds();

        expect($result->requests()->successful()->count())
            ->toBeGreaterThan(0);
    })->skip('Configure API URL for stress testing');

    /**
     * Example: Test gradual load increase
     */
    it('handles gradually increasing load', function () {
        $result = stress('https://example.com')
            ->concurrently(1)
            ->for(2)->seconds();

        $initialAvg = $result->requests()->duration()->average();

        $result = stress('https://example.com')
            ->concurrently(10)
            ->for(2)->seconds();

        $loadedAvg = $result->requests()->duration()->average();

        // Response time shouldn't increase more than 3x under load
        expect($loadedAvg)->toBeLessThan($initialAvg * 3);
    })->skip('Configure base URL for stress testing');
});

/**
 * In-Memory Stress Tests
 *
 * These tests don't require network access and test internal components.
 */
describe('In-Memory Stress Tests', function () {
    it('handles rapid task creation', function () {
        $start = microtime(true);
        $tasks = [];

        for ($i = 0; $i < 1000; $i++) {
            $tasks[] = \LaraForge\Agents\Task::create(
                'feature',
                "Task {$i}",
                "Description for task {$i}"
            );
        }

        $duration = microtime(true) - $start;

        expect(count($tasks))->toBe(1000)
            ->and($duration)->toBeLessThan(1.0); // Less than 1 second
    });

    it('handles rapid feature creation', function () {
        $start = microtime(true);
        $features = [];

        for ($i = 0; $i < 1000; $i++) {
            $features[] = \LaraForge\Project\Feature::create(
                "Feature {$i}",
                "Description for feature {$i}"
            );
        }

        $duration = microtime(true) - $start;

        expect(count($features))->toBe(1000)
            ->and($duration)->toBeLessThan(1.0);
    });

    it('handles rapid validation result creation and merging', function () {
        $start = microtime(true);
        $results = [];

        for ($i = 0; $i < 500; $i++) {
            $results[] = new \LaraForge\Skills\ValidationResult(
                errors: ["field_{$i}" => ["Error {$i}"]],
                warnings: ["field_{$i}" => ["Warning {$i}"]]
            );
        }

        // Merge all results
        $merged = array_reduce(
            $results,
            fn ($carry, $result) => $carry->merge($result),
            new \LaraForge\Skills\ValidationResult([], [])
        );

        $duration = microtime(true) - $start;

        expect(count($merged->allErrors()))->toBe(500)
            ->and(count($merged->allWarnings()))->toBe(500)
            ->and($duration)->toBeLessThan(1.0);
    });

    it('handles large array serialization', function () {
        $task = \LaraForge\Agents\Task::create(
            'feature',
            'Large Task',
            str_repeat('Description ', 1000)
        );

        // Add many metadata entries
        for ($i = 0; $i < 100; $i++) {
            $task->setMetadata("key_{$i}", str_repeat("value_{$i} ", 100));
        }

        $start = microtime(true);

        // Serialize and deserialize 100 times
        for ($i = 0; $i < 100; $i++) {
            $array = $task->toArray();
            $restored = \LaraForge\Agents\Task::fromArray($array);
        }

        $duration = microtime(true) - $start;

        expect($duration)->toBeLessThan(1.0);
    });

    it('handles concurrent skill registry operations', function () {
        $laraforge = laraforge(createTempDirectory());
        $registry = new \LaraForge\Skills\SkillRegistry($laraforge);

        $start = microtime(true);

        // Simulate many lookup operations
        for ($i = 0; $i < 10000; $i++) {
            $registry->get('non-existent-skill');
            $registry->all();
            $registry->categories();
        }

        $duration = microtime(true) - $start;

        expect($duration)->toBeLessThan(2.0);
    });
});

/**
 * Memory Stress Tests
 */
describe('Memory Stress Tests', function () {
    it('does not leak memory during task operations', function () {
        $initialMemory = memory_get_usage(true);

        for ($i = 0; $i < 1000; $i++) {
            $task = \LaraForge\Agents\Task::create('feature', "Task {$i}");
            $array = $task->toArray();
            \LaraForge\Agents\Task::fromArray($array);
            unset($task, $array);
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage(true);

        // Memory growth should be less than 10MB
        $memoryGrowth = $finalMemory - $initialMemory;

        expect($memoryGrowth)->toBeLessThan(10 * 1024 * 1024);
    });

    it('does not leak memory during feature operations', function () {
        $initialMemory = memory_get_usage(true);

        for ($i = 0; $i < 1000; $i++) {
            $feature = \LaraForge\Project\Feature::create("Feature {$i}");
            $feature->setProgress(50);
            $feature->addTag('test');
            $feature->addDocument('prd', '/path/to/prd.yaml');
            $array = $feature->toArray();
            \LaraForge\Project\Feature::fromArray($array);
            unset($feature, $array);
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage(true);

        $memoryGrowth = $finalMemory - $initialMemory;

        expect($memoryGrowth)->toBeLessThan(10 * 1024 * 1024);
    });
});
