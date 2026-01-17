<?php

declare(strict_types=1);

use LaraForge\LaraForge;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(\LaraForge\Tests\TestCase::class)->in('Feature', 'Unit', 'Property', 'Stress');

/*
|--------------------------------------------------------------------------
| Arch Testing
|--------------------------------------------------------------------------
|
| Architecture tests don't need the TestCase base class.
| They are configured directly through Pest's arch() function.
|
*/

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

expect()->extend('toBeValidSkill', function () {
    return $this
        ->toBeInstanceOf(\LaraForge\Skills\Contracts\SkillInterface::class)
        ->and($this->value->identifier())->toBeString()->not->toBeEmpty()
        ->and($this->value->name())->toBeString()->not->toBeEmpty()
        ->and($this->value->description())->toBeString()->not->toBeEmpty();
});

expect()->extend('toBeSuccessfulResult', function () {
    return $this
        ->toBeInstanceOf(\LaraForge\Skills\SkillResult::class)
        ->and($this->value->isSuccess())->toBeTrue()
        ->and($this->value->error())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function laraforge(?string $workingDirectory = null): LaraForge
{
    return new LaraForge($workingDirectory ?? sys_get_temp_dir().'/laraforge-test-'.uniqid());
}

function createTempDirectory(): string
{
    $path = sys_get_temp_dir().'/laraforge-test-'.uniqid();
    mkdir($path, 0755, true);

    return $path;
}

/**
 * Get the testing configuration.
 *
 * @return array<string, mixed>
 */
function testingConfig(): array
{
    static $config = null;

    if ($config === null) {
        $configPath = __DIR__.'/testing.config.php';
        $config = file_exists($configPath) ? require $configPath : [];
    }

    return $config;
}

/**
 * Check if a test suite is enabled.
 */
function testSuiteEnabled(string $suite): bool
{
    $config = testingConfig();

    return ($config[$suite]['enabled'] ?? false) === true;
}
