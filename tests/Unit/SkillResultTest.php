<?php

declare(strict_types=1);

use LaraForge\Skills\SkillResult;
use LaraForge\Skills\ValidationResult;

describe('SkillResult', function () {
    it('creates a successful result', function () {
        $result = SkillResult::success(
            output: 'Test output',
            artifacts: ['file.php' => '/path/to/file.php'],
            nextSteps: [['skill' => 'next-skill', 'reason' => 'Continue']],
            metadata: ['key' => 'value'],
        );

        expect($result->isSuccess())->toBeTrue()
            ->and($result->output())->toBe('Test output')
            ->and($result->artifacts())->toHaveKey('file.php')
            ->and($result->nextSteps())->toHaveCount(1)
            ->and($result->error())->toBeNull()
            ->and($result->metadata())->toHaveKey('key');
    });

    it('creates a failure result', function () {
        $result = SkillResult::failure(
            error: 'Something went wrong',
            output: 'Partial output',
            artifacts: ['log' => '/path/to/log'],
            metadata: ['debug' => true],
        );

        expect($result->isSuccess())->toBeFalse()
            ->and($result->error())->toBe('Something went wrong')
            ->and($result->output())->toBe('Partial output')
            ->and($result->artifacts())->toHaveKey('log')
            ->and($result->metadata())->toHaveKey('debug');
    });

    it('converts to array', function () {
        $result = SkillResult::success('output', ['file' => 'path']);

        $array = $result->toArray();

        expect($array)->toHaveKey('success')
            ->and($array['success'])->toBeTrue()
            ->and($array)->toHaveKey('output')
            ->and($array)->toHaveKey('artifacts')
            ->and($array)->toHaveKey('next_steps')
            ->and($array)->toHaveKey('error')
            ->and($array)->toHaveKey('metadata');
    });

    it('handles empty values correctly', function () {
        $result = SkillResult::success();

        expect($result->isSuccess())->toBeTrue()
            ->and($result->output())->toBeNull()
            ->and($result->artifacts())->toBe([])
            ->and($result->nextSteps())->toBe([])
            ->and($result->metadata())->toBe([]);
    });
});

describe('ValidationResult', function () {
    it('creates a valid result', function () {
        $result = ValidationResult::valid();

        expect($result->isValid())->toBeTrue()
            ->and($result->errors())->toBe([])
            ->and($result->warnings())->toBe([])
            ->and($result->hasWarnings())->toBeFalse();
    });

    it('creates an invalid result with errors', function () {
        $result = ValidationResult::invalid([
            'name' => ['Name is required', 'Name must be at least 3 characters'],
            'email' => ['Invalid email format'],
        ]);

        expect($result->isValid())->toBeFalse()
            ->and($result->errors())->toHaveKey('name')
            ->and($result->errors()['name'])->toHaveCount(2)
            ->and($result->errors())->toHaveKey('email');
    });

    it('creates a result with warnings only', function () {
        $result = ValidationResult::withWarnings([
            'deprecated' => ['This parameter is deprecated'],
        ]);

        expect($result->isValid())->toBeTrue()
            ->and($result->hasWarnings())->toBeTrue()
            ->and($result->warnings())->toHaveKey('deprecated');
    });

    it('returns all errors as flat array', function () {
        $result = ValidationResult::invalid([
            'name' => ['Required', 'Too short'],
            'email' => ['Invalid'],
        ]);

        $allErrors = $result->allErrors();

        expect($allErrors)->toHaveCount(3)
            ->and($allErrors)->toContain('name: Required')
            ->and($allErrors)->toContain('name: Too short')
            ->and($allErrors)->toContain('email: Invalid');
    });

    it('returns all warnings as flat array', function () {
        $result = ValidationResult::withWarnings([
            'old_param' => ['Deprecated'],
            'slow_method' => ['Consider optimizing'],
        ]);

        $allWarnings = $result->allWarnings();

        expect($allWarnings)->toHaveCount(2)
            ->and($allWarnings)->toContain('old_param: Deprecated')
            ->and($allWarnings)->toContain('slow_method: Consider optimizing');
    });

    it('merges validation results', function () {
        $result1 = ValidationResult::invalid(['name' => ['Required']]);
        $result2 = ValidationResult::invalid(
            ['email' => ['Invalid']],
            ['warning' => ['Something to note']],
        );

        $merged = $result1->merge($result2);

        expect($merged->isValid())->toBeFalse()
            ->and($merged->errors())->toHaveKey('name')
            ->and($merged->errors())->toHaveKey('email')
            ->and($merged->hasWarnings())->toBeTrue();
    });

    it('converts to array', function () {
        $result = ValidationResult::invalid(
            ['name' => ['Required']],
            ['hint' => ['Consider adding description']],
        );

        $array = $result->toArray();

        expect($array)->toHaveKey('valid')
            ->and($array['valid'])->toBeFalse()
            ->and($array)->toHaveKey('errors')
            ->and($array)->toHaveKey('warnings');
    });
});
