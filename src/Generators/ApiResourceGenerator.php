<?php

declare(strict_types=1);

namespace LaraForge\Generators;

use LaraForge\Support\Generator;
use LaraForge\Support\TddAwareGenerator;

final class ApiResourceGenerator extends Generator
{
    use TddAwareGenerator;

    public function identifier(): string
    {
        return 'api-resource';
    }

    public function name(): string
    {
        return 'API Resource';
    }

    public function description(): string
    {
        return 'Generates Laravel API Resource classes with optional OpenAPI documentation';
    }

    public function supportsTdd(): bool
    {
        return true;
    }

    public function options(): array
    {
        return [
            'model' => [
                'type' => 'string',
                'description' => 'The model name (e.g., "User")',
                'required' => true,
            ],
            'attributes' => [
                'type' => 'array',
                'description' => 'Attributes to include in the resource',
                'required' => false,
                'default' => [],
            ],
            'include_collection' => [
                'type' => 'boolean',
                'description' => 'Generate a ResourceCollection class',
                'required' => false,
                'default' => false,
            ],
            'openapi' => [
                'type' => 'boolean',
                'description' => 'Include OpenAPI schema annotations',
                'required' => false,
                'default' => false,
            ],
            'style' => [
                'type' => 'string',
                'description' => 'Implementation style: "regular" or "tdd"',
                'required' => false,
                'default' => 'regular',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    public function generate(array $options = []): array
    {
        $model = $this->studly($options['model']);
        $attributes = $options['attributes'] ?? [];
        $includeCollection = $options['include_collection'] ?? false;
        $openapi = $options['openapi'] ?? false;

        if ($this->isTddMode($options)) {
            return $this->generateWithTdd($options);
        }

        return $this->generateRegular($model, $attributes, $includeCollection, $openapi);
    }

    /**
     * Generate files in regular mode.
     *
     * @param  array<string>  $attributes
     * @return array<string>
     */
    private function generateRegular(
        string $model,
        array $attributes,
        bool $includeCollection,
        bool $openapi,
    ): array {
        $generatedFiles = [];

        // Generate resource class
        $resourceClassName = "{$model}Resource";
        $resourceContent = $this->renderStub('api-resource', [
            'className' => $resourceClassName,
            'modelClass' => $model,
            'attributes' => $attributes,
            'noAttributes' => empty($attributes),
            'openapi' => $openapi,
        ]);
        $generatedFiles[] = $this->writeFile(
            "app/Http/Resources/{$resourceClassName}.php",
            $resourceContent,
        );

        // Generate collection class if requested
        if ($includeCollection) {
            $collectionClassName = "{$model}Collection";
            $collectionContent = $this->renderStub('api-resource-collection', [
                'className' => $collectionClassName,
                'resourceClass' => $resourceClassName,
                'modelClass' => $model,
                'openapi' => $openapi,
            ]);
            $generatedFiles[] = $this->writeFile(
                "app/Http/Resources/{$collectionClassName}.php",
                $collectionContent,
            );
        }

        return $generatedFiles;
    }

    /**
     * Generate test files for TDD mode.
     *
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    protected function generateTests(array $options): array
    {
        $model = $this->studly($options['model']);
        $attributes = $options['attributes'] ?? [];
        $includeCollection = $options['include_collection'] ?? false;

        $generatedFiles = [];

        // Generate default data for test model
        $defaultData = empty($attributes) ? "'id' => 1" : implode(', ', array_map(
            fn ($attr) => "'{$attr}' => 'test'",
            $attributes,
        ));

        // Generate resource test
        $resourceClassName = "{$model}Resource";
        $testContent = $this->renderStub('api-resource-test', [
            'className' => $resourceClassName,
            'modelClass' => $model,
            'attributes' => $attributes,
            'hasAttributes' => ! empty($attributes),
            'noAttributes' => empty($attributes),
            'defaultData' => $defaultData,
        ]);
        $generatedFiles[] = $this->writeFile(
            "tests/Unit/Http/Resources/{$resourceClassName}Test.php",
            $testContent,
        );

        // Generate collection test if requested
        if ($includeCollection) {
            $collectionClassName = "{$model}Collection";
            $collectionTestContent = $this->renderStub('api-resource-collection-test', [
                'className' => $collectionClassName,
                'resourceClass' => $resourceClassName,
                'modelClass' => $model,
            ]);
            $generatedFiles[] = $this->writeFile(
                "tests/Unit/Http/Resources/{$collectionClassName}Test.php",
                $collectionTestContent,
            );
        }

        return $generatedFiles;
    }

    /**
     * Generate implementation files for TDD mode.
     *
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    protected function generateImplementation(array $options): array
    {
        $model = $this->studly($options['model']);
        $attributes = $options['attributes'] ?? [];
        $includeCollection = $options['include_collection'] ?? false;
        $openapi = $options['openapi'] ?? false;

        return $this->generateRegular($model, $attributes, $includeCollection, $openapi);
    }
}
