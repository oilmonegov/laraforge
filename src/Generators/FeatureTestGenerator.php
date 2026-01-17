<?php

declare(strict_types=1);

namespace LaraForge\Generators;

use LaraForge\Criteria\AcceptanceCriteria;
use LaraForge\Support\TestGenerator;

final class FeatureTestGenerator extends TestGenerator
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    private const HTTP_METHOD_PATTERNS = [
        'get' => ['list', 'view', 'show', 'fetch', 'retrieve', 'display', 'read', 'index'],
        'post' => ['create', 'add', 'store', 'submit', 'register', 'new'],
        'put' => ['update', 'modify', 'change', 'edit', 'replace'],
        'patch' => ['patch', 'partial'],
        'delete' => ['delete', 'remove', 'destroy', 'archive'],
    ];

    public function identifier(): string
    {
        return 'feature-test';
    }

    public function name(): string
    {
        return 'Feature Test';
    }

    public function description(): string
    {
        return 'Generates Pest feature tests from acceptance criteria';
    }

    public function options(): array
    {
        return [
            'feature' => [
                'type' => 'string',
                'description' => 'The feature name',
                'required' => true,
            ],
            'criteria_file' => [
                'type' => 'string',
                'description' => 'Path to criteria YAML/JSON file',
                'required' => false,
                'default' => null,
            ],
            'test_type' => [
                'type' => 'string',
                'description' => 'Test type: "feature" or "unit"',
                'required' => false,
                'default' => 'feature',
            ],
            'http_methods' => [
                'type' => 'array',
                'description' => 'HTTP methods to scaffold (get, post, put, patch, delete)',
                'required' => false,
                'default' => [],
            ],
            'resource' => [
                'type' => 'string',
                'description' => 'Resource name for endpoints',
                'required' => false,
                'default' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    public function generate(array $options = []): array
    {
        $feature = $this->studly($options['feature']);
        $criteriaFile = $options['criteria_file'] ?? null;
        $testType = $options['test_type'] ?? 'feature';
        $httpMethods = $options['http_methods'] ?? [];
        $resource = $options['resource'] ?? null;

        $generatedFiles = [];

        // If HTTP methods provided, generate HTTP endpoint tests
        if (! empty($httpMethods) || $resource !== null) {
            $generatedFiles[] = $this->generateHttpTest($feature, $httpMethods, $resource, $testType);

            return $generatedFiles;
        }

        // Load criteria if file provided
        $criteria = null;
        if ($criteriaFile !== null) {
            $criteriaPath = $this->resolveCriteriaPath($criteriaFile);
            if ($criteriaPath !== null && $this->laraforge->criteriaLoader()->exists($criteriaPath)) {
                $criteria = $this->laraforge->criteriaLoader()->load($criteriaPath);
            }
        }

        // Try to find criteria by feature name
        if ($criteria === null) {
            $criteriaPath = $this->laraforge->criteriaLoader()->find(
                $this->kebab($feature),
                $this->laraforge->criteriaLoader()->defaultDirectory(),
            );

            if ($criteriaPath !== null) {
                $criteria = $this->laraforge->criteriaLoader()->load($criteriaPath);
            }
        }

        // Generate test from criteria or empty template
        if ($criteria !== null) {
            $generatedFiles[] = $this->generateFromCriteria($criteria, $feature, $testType);
        } else {
            $generatedFiles[] = $this->generateEmptyTest($feature, $testType);
        }

        return $generatedFiles;
    }

    /**
     * Generate test file from acceptance criteria.
     */
    private function generateFromCriteria(
        AcceptanceCriteria $criteria,
        string $feature,
        string $testType,
    ): string {
        $testCasesWithHttp = [];
        $testCasesWithoutHttp = [];

        foreach ($criteria->all() as $criterion) {
            $testCase = $this->buildTestCase($criterion);

            // Infer HTTP method from description
            $httpMethod = $this->inferHttpMethod($criterion->description);
            if ($httpMethod !== null) {
                $testCase['httpMethod'] = $httpMethod;
                $testCase['endpoint'] = $this->inferEndpoint($criterion->description, $feature);
                $testCase['statusCode'] = $this->inferStatusCode($httpMethod, $criterion->description);
                $testCasesWithHttp[] = $testCase;
            } else {
                $testCasesWithoutHttp[] = $testCase;
            }
        }

        $testDirectory = $this->getTestDirectory($testType);
        $testPath = "{$testDirectory}/{$feature}Test.php";

        $content = $this->renderStub('feature-test', [
            'feature' => $criteria->feature,
            'testCasesWithHttp' => $testCasesWithHttp,
            'testCasesWithoutHttp' => $testCasesWithoutHttp,
            'uses' => [],
        ]);

        return $this->writeFile($testPath, $content);
    }

    /**
     * Generate empty test file.
     */
    private function generateEmptyTest(string $feature, string $testType): string
    {
        $testDirectory = $this->getTestDirectory($testType);
        $testPath = "{$testDirectory}/{$feature}Test.php";

        return $this->generateEmptyPestTest($feature, $testPath);
    }

    /**
     * Generate HTTP endpoint tests.
     *
     * @param  array<string>  $httpMethods
     */
    private function generateHttpTest(
        string $feature,
        array $httpMethods,
        ?string $resource,
        string $testType,
    ): string {
        $resourceName = $resource ?? $this->kebab($feature);
        $endpointsWithValidation = [];
        $endpointsGetOnly = [];
        $endpointsDeleteOnly = [];

        // If no HTTP methods specified, generate all standard CRUD endpoints
        if (empty($httpMethods)) {
            $httpMethods = ['get', 'post', 'put', 'delete'];
        }

        foreach ($httpMethods as $method) {
            $method = strtolower($method);
            if (! in_array($method, self::HTTP_METHODS, true)) {
                continue;
            }

            $endpoint = $this->buildEndpoint($method, $resourceName);

            // Categorize endpoints
            if ($endpoint['hasValidation']) {
                $endpointsWithValidation[] = $endpoint;
            } elseif ($method === 'delete') {
                $endpointsDeleteOnly[] = $endpoint;
            } else {
                $endpointsGetOnly[] = $endpoint;
            }
        }

        $testDirectory = $this->getTestDirectory($testType);
        $testPath = "{$testDirectory}/{$feature}Test.php";

        $content = $this->renderStub('feature-test-http', [
            'feature' => $feature,
            'endpointsWithValidation' => $endpointsWithValidation,
            'endpointsGetOnly' => $endpointsGetOnly,
            'endpointsDeleteOnly' => $endpointsDeleteOnly,
            'uses' => [],
        ]);

        return $this->writeFile($testPath, $content);
    }

    /**
     * Build endpoint data for HTTP test.
     *
     * @return array<string, mixed>
     */
    private function buildEndpoint(string $method, string $resource): array
    {
        $path = match ($method) {
            'get' => "/api/{$resource}",
            'post' => "/api/{$resource}",
            'put', 'patch' => "/api/{$resource}/{id}",
            'delete' => "/api/{$resource}/{id}",
            default => "/api/{$resource}",
        };

        $successStatus = match ($method) {
            'post' => 201,
            'delete' => 204,
            default => 200,
        };

        return [
            'method' => strtoupper($method),
            'methodLower' => $method,
            'path' => $path,
            'successStatus' => $successStatus,
            'requiresAuth' => true,
            'hasValidation' => in_array($method, ['post', 'put', 'patch'], true),
            'returnsJson' => $method !== 'delete',
        ];
    }

    /**
     * Infer HTTP method from criterion description.
     */
    private function inferHttpMethod(string $description): ?string
    {
        $lowerDescription = strtolower($description);

        foreach (self::HTTP_METHOD_PATTERNS as $method => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($lowerDescription, $pattern)) {
                    return $method;
                }
            }
        }

        return null;
    }

    /**
     * Infer endpoint from description and feature.
     */
    private function inferEndpoint(string $description, string $feature): string
    {
        $resource = $this->kebab($feature);

        return "/api/{$resource}";
    }

    /**
     * Infer expected status code.
     */
    private function inferStatusCode(string $method, string $description): int
    {
        $lowerDescription = strtolower($description);

        // Check for error scenarios
        if (str_contains($lowerDescription, 'fail') ||
            str_contains($lowerDescription, 'invalid') ||
            str_contains($lowerDescription, 'error')) {
            return str_contains($lowerDescription, 'not found') ? 404 : 422;
        }

        // Check for unauthorized
        if (str_contains($lowerDescription, 'unauthorized') ||
            str_contains($lowerDescription, 'unauthenticated')) {
            return 401;
        }

        // Check for forbidden
        if (str_contains($lowerDescription, 'forbidden') ||
            str_contains($lowerDescription, 'denied')) {
            return 403;
        }

        return match ($method) {
            'post' => 201,
            'delete' => 204,
            default => 200,
        };
    }

    /**
     * Resolve criteria file path.
     */
    private function resolveCriteriaPath(string $criteriaFile): ?string
    {
        // Absolute path
        if (str_starts_with($criteriaFile, '/')) {
            return $criteriaFile;
        }

        // Relative to working directory
        $relativePath = $this->laraforge->workingDirectory().'/'.$criteriaFile;
        if (file_exists($relativePath)) {
            return $relativePath;
        }

        // Check in criteria directory
        $criteriaDir = $this->laraforge->criteriaLoader()->defaultDirectory();
        $criteriaDirPath = $criteriaDir.'/'.$criteriaFile;
        if (file_exists($criteriaDirPath)) {
            return $criteriaDirPath;
        }

        return null;
    }
}
