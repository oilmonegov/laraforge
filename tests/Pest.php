<?php

declare(strict_types=1);

use LaraForge\LaraForge;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidGenerator', function () {
    return $this
        ->toBeInstanceOf(\LaraForge\Contracts\GeneratorInterface::class)
        ->and($this->value->identifier())->toBeString()->not->toBeEmpty()
        ->and($this->value->name())->toBeString()->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function laraforge(?string $workingDirectory = null): LaraForge
{
    return new LaraForge($workingDirectory ?? sys_get_temp_dir() . '/laraforge-test-' . uniqid());
}

function createTempDirectory(): string
{
    $path = sys_get_temp_dir() . '/laraforge-test-' . uniqid();
    mkdir($path, 0755, true);

    return $path;
}
