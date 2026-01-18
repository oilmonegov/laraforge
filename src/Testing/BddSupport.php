<?php

declare(strict_types=1);

namespace LaraForge\Testing;

/**
 * BDD (Behavior-Driven Development) Support
 *
 * Provides configuration and helpers for implementing BDD testing
 * patterns with Pest PHP using Given-When-Then syntax.
 */
final class BddSupport
{
    /**
     * Get Pest BDD test template.
     */
    public static function getPestBddTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use function Pest\Laravel\{actingAs, get, post, delete};

describe('{{ featureName }}', function () {
    beforeEach(function () {
        // Setup shared test state
    });

    describe('{{ scenario }}', function () {
        it('{{ behavior }}', function () {
            // Given: Set up the initial context
            {{ given }}

            // When: Perform the action
            {{ when }}

            // Then: Assert the expected outcome
            {{ then }}
        });
    });
});
PHP;
    }

    /**
     * Get BDD test examples for common patterns.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getPatterns(): array
    {
        return [
            'authentication' => [
                'description' => 'User authentication scenarios',
                'examples' => [
                    [
                        'scenario' => 'User Login',
                        'behavior' => 'allows a user to log in with valid credentials',
                        'given' => '$user = User::factory()->create();',
                        'when' => '$response = post(\'/login\', [\'email\' => $user->email, \'password\' => \'password\']);',
                        'then' => '$response->assertRedirect(\'/dashboard\'); $this->assertAuthenticatedAs($user);',
                    ],
                    [
                        'scenario' => 'User Login - Invalid Credentials',
                        'behavior' => 'rejects login with invalid credentials',
                        'given' => '$user = User::factory()->create();',
                        'when' => '$response = post(\'/login\', [\'email\' => $user->email, \'password\' => \'wrong\']);',
                        'then' => '$response->assertSessionHasErrors([\'email\']); $this->assertGuest();',
                    ],
                ],
            ],
            'authorization' => [
                'description' => 'Authorization and policy scenarios',
                'examples' => [
                    [
                        'scenario' => 'Resource Access',
                        'behavior' => 'allows owners to view their resource',
                        'given' => '$user = User::factory()->create(); $resource = Resource::factory()->for($user)->create();',
                        'when' => '$response = actingAs($user)->get("/resources/{$resource->id}");',
                        'then' => '$response->assertOk(); $response->assertSee($resource->name);',
                    ],
                    [
                        'scenario' => 'Resource Access - Forbidden',
                        'behavior' => 'denies access to resources owned by others',
                        'given' => '$user = User::factory()->create(); $other = User::factory()->create(); $resource = Resource::factory()->for($other)->create();',
                        'when' => '$response = actingAs($user)->get("/resources/{$resource->id}");',
                        'then' => '$response->assertForbidden();',
                    ],
                ],
            ],
            'crud' => [
                'description' => 'CRUD operation scenarios',
                'examples' => [
                    [
                        'scenario' => 'Create Resource',
                        'behavior' => 'creates a new resource with valid data',
                        'given' => '$user = User::factory()->create(); $data = Resource::factory()->make()->toArray();',
                        'when' => '$response = actingAs($user)->post(\'/resources\', $data);',
                        'then' => '$response->assertRedirect(); $this->assertDatabaseHas(\'resources\', [\'name\' => $data[\'name\']]);',
                    ],
                    [
                        'scenario' => 'Validation Error',
                        'behavior' => 'rejects invalid data with validation errors',
                        'given' => '$user = User::factory()->create();',
                        'when' => '$response = actingAs($user)->post(\'/resources\', [\'name\' => \'\']);',
                        'then' => '$response->assertSessionHasErrors([\'name\']);',
                    ],
                ],
            ],
            'api' => [
                'description' => 'API endpoint scenarios',
                'examples' => [
                    [
                        'scenario' => 'API List',
                        'behavior' => 'returns paginated resources as JSON',
                        'given' => '$user = User::factory()->create(); Resource::factory()->count(15)->for($user)->create();',
                        'when' => '$response = actingAs($user)->getJson(\'/api/resources\');',
                        'then' => '$response->assertOk(); $response->assertJsonStructure([\'data\', \'meta\', \'links\']);',
                    ],
                    [
                        'scenario' => 'API Create',
                        'behavior' => 'creates resource and returns 201',
                        'given' => '$user = User::factory()->create(); $data = Resource::factory()->make()->toArray();',
                        'when' => '$response = actingAs($user)->postJson(\'/api/resources\', $data);',
                        'then' => '$response->assertCreated(); $response->assertJsonPath(\'data.name\', $data[\'name\']);',
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate BDD test file from acceptance criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    public static function generateFromCriteria(array $criteria): string
    {
        $tests = [];
        $feature = $criteria['feature'] ?? 'Feature';

        $tests[] = '<?php';
        $tests[] = '';
        $tests[] = 'declare(strict_types=1);';
        $tests[] = '';
        $tests[] = "describe('{$feature}', function () {";

        /** @var array<array{name: string, given?: string, when?: string, then?: string, scenarios?: array<array{name: string, given?: array<string>, when?: array<string>, then?: array<string>}>}> $contracts */
        $contracts = $criteria['contracts'] ?? [];

        foreach ($contracts as $contract) {
            $name = $contract['name'];

            // Simple given-when-then format
            if (isset($contract['given'], $contract['when'], $contract['then'])) {
                $tests[] = "    it('{$name}', function () {";
                $tests[] = '        // Given: '.$contract['given'];
                $tests[] = '        // When: '.$contract['when'];
                $tests[] = '        // Then: '.$contract['then'];
                $tests[] = '    });';
                $tests[] = '';

                continue;
            }

            // Multiple scenarios format
            if (isset($contract['scenarios'])) {
                $tests[] = "    describe('{$name}', function () {";

                foreach ($contract['scenarios'] as $scenario) {
                    $scenarioName = $scenario['name'];
                    $tests[] = "        it('{$scenarioName}', function () {";

                    if (isset($scenario['given'])) {
                        $tests[] = '            // Given';
                        foreach ($scenario['given'] as $given) {
                            $tests[] = "            // - {$given}";
                        }
                    }

                    if (isset($scenario['when'])) {
                        $tests[] = '            // When';
                        foreach ($scenario['when'] as $when) {
                            $tests[] = "            // - {$when}";
                        }
                    }

                    if (isset($scenario['then'])) {
                        $tests[] = '            // Then';
                        foreach ($scenario['then'] as $then) {
                            $tests[] = "            // - {$then}";
                        }
                    }

                    $tests[] = '        });';
                    $tests[] = '';
                }

                $tests[] = '    });';
                $tests[] = '';
            }
        }

        $tests[] = '});';

        return implode("\n", $tests);
    }

    /**
     * Get Pest helper functions for BDD.
     */
    public static function getPestHelpers(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

/**
 * BDD Helper Functions for Pest
 * Add to tests/Pest.php
 */

/**
 * Create a test scenario with given-when-then structure.
 */
function scenario(string $name): object
{
    return new class($name) {
        private string $name;
        private mixed $context = null;
        private mixed $result = null;

        public function __construct(string $name)
        {
            $this->name = $name;
        }

        public function given(callable $setup): self
        {
            $this->context = $setup();
            return $this;
        }

        public function when(callable $action): self
        {
            $this->result = $action($this->context);
            return $this;
        }

        public function then(callable $assertion): void
        {
            $assertion($this->result, $this->context);
        }
    };
}

/**
 * Create test data builder.
 */
function given(string $description, callable $setup): mixed
{
    return $setup();
}

/**
 * Perform action and capture result.
 */
function when(string $description, callable $action, mixed ...$args): mixed
{
    return $action(...$args);
}

/**
 * Assert expected outcome.
 */
function then(string $description, callable $assertion, mixed ...$args): void
{
    $assertion(...$args);
}
PHP;
    }

    /**
     * Get recommended testing packages for BDD.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRecommendedPackages(): array
    {
        return [
            'pestphp/pest' => [
                'description' => 'An elegant PHP testing framework (already included)',
                'version' => '^4.0',
                'notes' => 'Core testing framework with BDD-style describe/it syntax',
            ],
            'pestphp/pest-plugin-faker' => [
                'description' => 'Faker integration for Pest',
                'version' => '^3.0',
                'use_case' => 'Generate realistic test data',
            ],
            'pestphp/pest-plugin-laravel' => [
                'description' => 'Laravel-specific testing helpers',
                'version' => '^3.0',
                'use_case' => 'Laravel testing shortcuts and assertions',
            ],
            'spatie/pest-plugin-snapshots' => [
                'description' => 'Snapshot testing for Pest',
                'version' => '^2.0',
                'use_case' => 'Assert complex data structures against snapshots',
            ],
        ];
    }

    /**
     * Get configuration for BDD test directories.
     *
     * @return array<string, mixed>
     */
    public static function getTestStructure(): array
    {
        return [
            'tests' => [
                'Unit' => [
                    'description' => 'Unit tests for isolated components',
                    'pattern' => '*Test.php',
                ],
                'Feature' => [
                    'description' => 'Feature/integration tests (BDD scenarios)',
                    'pattern' => '*Test.php',
                    'subdirectories' => [
                        'Auth' => 'Authentication scenarios',
                        'Api' => 'API endpoint scenarios',
                        'Web' => 'Web interface scenarios',
                    ],
                ],
                'Arch' => [
                    'description' => 'Architecture tests',
                    'pattern' => '*Test.php',
                ],
                'Browser' => [
                    'description' => 'End-to-end browser tests',
                    'pattern' => '*Test.php',
                ],
            ],
        ];
    }
}
