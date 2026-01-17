<?php

declare(strict_types=1);

/**
 * Code Quality Tests
 *
 * Enforces code quality standards and best practices.
 * These rules ensure the codebase maintains high quality and follows
 * established patterns.
 */
arch('source files do not use debug functions')
    ->expect('LaraForge')
    ->not->toUse([
        'dd',
        'dump',
        'var_dump',
        'print_r',
        'ray',
    ]);

arch('source files do not use die or exit')
    ->expect('LaraForge')
    ->not->toUse([
        'die',
        'exit',
    ]);

/*
|--------------------------------------------------------------------------
| Value Objects Must Be Final
|--------------------------------------------------------------------------
|
| Value objects should be final to prevent inheritance that could
| break their immutability guarantees and equality semantics.
|
*/

arch('SkillResult is a final value object')
    ->expect('LaraForge\Skills\SkillResult')
    ->toBeFinal();

arch('ValidationResult is a final value object')
    ->expect('LaraForge\Skills\ValidationResult')
    ->toBeFinal();

arch('StepResult is a final value object')
    ->expect('LaraForge\Workflows\StepResult')
    ->toBeFinal();

arch('Recommendation is a final value object')
    ->expect('LaraForge\Workflows\Recommendation')
    ->toBeFinal();
