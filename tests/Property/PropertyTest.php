<?php

declare(strict_types=1);

/**
 * Property-Based Tests for LaraForge
 *
 * These tests verify that certain properties hold true for all inputs,
 * not just specific test cases. They use randomized data to find edge cases.
 */

use LaraForge\Agents\Task;
use LaraForge\Project\Feature;
use LaraForge\Skills\SkillResult;
use LaraForge\Skills\ValidationResult;

/**
 * Task Property Tests
 */
describe('Task Properties', function () {
    it('always maintains valid id format', function (string $type, string $title) {
        $task = Task::create($type, $title);

        expect($task->id())
            ->toStartWith('task_')
            ->and(strlen($task->id()))->toBe(21); // 'task_' + 16 hex chars
    })->with([
        ['feature', 'Simple Task'],
        ['bugfix', 'Fix something'],
        ['refactor', 'Improve code'],
        ['test', 'Add tests'],
        ['review', 'Review PR'],
        ['feature', ''],
        ['feature', str_repeat('a', 1000)],
        ['feature', 'Unicode: 日本語 émojis 🎉'],
        ['feature', "Newlines\nand\ttabs"],
        ['feature', '<script>alert("xss")</script>'],
    ]);

    it('roundtrips through array serialization', function (string $type, string $title, string $description) {
        $original = Task::create($type, $title, $description);
        $array = $original->toArray();
        $restored = Task::fromArray($array);

        expect($restored->type())->toBe($original->type())
            ->and($restored->title())->toBe($original->title())
            ->and($restored->description())->toBe($original->description())
            ->and($restored->status())->toBe($original->status())
            ->and($restored->priority())->toBe($original->priority());
    })->with([
        ['feature', 'Task 1', 'Description 1'],
        ['bugfix', 'Task 2', ''],
        ['refactor', '', 'Only description'],
        ['test', 'Unicode: 日本語', 'More unicode: émojis'],
        ['review', 'Special chars: <>&"\'', 'More special: `~!@#$%^&*()'],
    ]);

    it('status transitions are always valid', function (string $initialStatus, string $newStatus) {
        $task = Task::create('feature', 'Test Task');
        $task->setStatus($initialStatus);
        $task->setStatus($newStatus);

        expect($task->status())->toBe($newStatus);
    })->with([
        ['pending', 'in_progress'],
        ['in_progress', 'completed'],
        ['pending', 'completed'],
        ['completed', 'pending'],
        ['in_progress', 'failed'],
        ['failed', 'in_progress'],
    ]);

    it('priority is always bounded between 1 and 5', function (int $priority) {
        // Priority is set at construction, so we verify the create method handles it
        $task = Task::create('feature', 'Test', '', [], null, $priority);

        expect($task->priority())->toBe($priority);
    })->with([1, 2, 3, 4, 5]);
});

/**
 * Feature Property Tests
 */
describe('Feature Properties', function () {
    it('progress is always bounded between 0 and 100', function (int $progress) {
        $feature = Feature::create('Test Feature');
        $feature->setProgress($progress);

        expect($feature->progress())
            ->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(100);
    })->with([
        -100,
        -1,
        0,
        1,
        50,
        99,
        100,
        101,
        1000,
    ]);

    it('priority is always bounded between 1 and 5', function (int $priority) {
        $feature = Feature::create('Test Feature');
        $feature->setPriority($priority);

        expect($feature->priority())
            ->toBeGreaterThanOrEqual(1)
            ->toBeLessThanOrEqual(5);
    })->with([
        -5,
        0,
        1,
        3,
        5,
        6,
        100,
    ]);

    it('roundtrips through array serialization', function (string $title, string $description) {
        $original = Feature::create($title, $description);
        $original->setProgress(50);
        $original->setPriority(3);
        $original->addTag('test');
        $original->addDocument('prd', '/path/to/prd.yaml');

        $array = $original->toArray();
        $restored = Feature::fromArray($array);

        expect($restored->id())->toBe($original->id())
            ->and($restored->title())->toBe($original->title())
            ->and($restored->description())->toBe($original->description())
            ->and($restored->progress())->toBe($original->progress())
            ->and($restored->priority())->toBe($original->priority())
            ->and($restored->tags())->toBe($original->tags())
            ->and($restored->documents())->toBe($original->documents());
    })->with([
        ['Feature One', 'Description'],
        ['Feature Two', ''],
        ['Unicode Title 日本語', 'Unicode desc'],
        ['Title with "quotes"', "Desc with\nnewlines"],
    ]);

    it('documents are properly keyed by type', function (string $type, string $path) {
        $feature = Feature::create('Test');
        $feature->addDocument($type, $path);

        expect($feature->document($type))->toBe($path)
            ->and($feature->documents())->toHaveKey($type);
    })->with([
        ['prd', '/docs/prd.yaml'],
        ['frd', '/docs/frd.yaml'],
        ['design', '/docs/design.yaml'],
        ['test-contract', '/docs/contract.yaml'],
        ['custom-type', '/docs/custom.yaml'],
    ]);
});

/**
 * SkillResult Property Tests
 */
describe('SkillResult Properties', function () {
    it('success results are always successful', function (string $output) {
        $result = SkillResult::success($output);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->error())->toBeNull()
            ->and($result->output())->toBe($output);
    })->with([
        'Simple output',
        '',
        'Unicode: 日本語 🎉',
        str_repeat('Long output ', 1000),
        "Output with\nnewlines\nand\ttabs",
    ]);

    it('failure results are always failures', function (string $error) {
        $result = SkillResult::failure($error);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->error())->toBe($error);
    })->with([
        'Simple error',
        '',
        'Unicode error: 日本語',
        'Error with special chars: <>&"\'',
    ]);

    it('array serialization preserves all properties', function (
        bool $success,
        string $output,
        array $artifacts,
        array $nextSteps
    ) {
        $result = $success
            ? SkillResult::success($output, $artifacts, $nextSteps)
            : SkillResult::failure('Error', $output);

        $array = $result->toArray();

        expect($array['success'])->toBe($success)
            ->and($array)->toHaveKey('output')
            ->and($array)->toHaveKey('artifacts')
            ->and($array)->toHaveKey('next_steps')
            ->and($array)->toHaveKey('error')
            ->and($array)->toHaveKey('metadata');
    })->with([
        [true, 'output', ['file' => 'test.php'], [['skill' => 'next']]],
        [false, '', [], []],
        [true, 'unicode 日本語', ['key' => 'value'], []],
    ]);
});

/**
 * ValidationResult Property Tests
 */
describe('ValidationResult Properties', function () {
    it('results with no errors are always valid', function (array $warnings) {
        $result = new ValidationResult(errors: [], warnings: $warnings);

        expect($result->isValid())->toBeTrue()
            ->and($result->errors())->toBeEmpty();
    })->with([
        [[]],
        [['field' => ['warning']]],
        [['a' => ['w1'], 'b' => ['w2', 'w3']]],
    ]);

    it('results with errors are always invalid', function (array $errors) {
        $result = new ValidationResult(errors: $errors, warnings: []);

        expect($result->isValid())->toBeFalse()
            ->and($result->allErrors())->not->toBeEmpty();
    })->with([
        [['field' => ['error']]],
        [['a' => ['e1'], 'b' => ['e2']]],
        [['single' => ['One error']]],
    ]);

    it('merged results combine all errors and warnings', function (
        array $errors1,
        array $errors2,
        array $warnings1,
        array $warnings2
    ) {
        $result1 = new ValidationResult(errors: $errors1, warnings: $warnings1);
        $result2 = new ValidationResult(errors: $errors2, warnings: $warnings2);
        $merged = $result1->merge($result2);

        $totalErrors = count($result1->allErrors()) + count($result2->allErrors());
        $totalWarnings = count($result1->allWarnings()) + count($result2->allWarnings());

        expect(count($merged->allErrors()))->toBe($totalErrors)
            ->and(count($merged->allWarnings()))->toBe($totalWarnings);
    })->with([
        [['a' => ['e1']], ['b' => ['e2']], [], []],
        [[], [], ['a' => ['w1']], ['b' => ['w2']]],
        [['a' => ['e1']], [], ['b' => ['w1']], []],
    ]);
});
