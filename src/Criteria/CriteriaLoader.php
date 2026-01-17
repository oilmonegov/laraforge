<?php

declare(strict_types=1);

namespace LaraForge\Criteria;

use LaraForge\Contracts\CriteriaLoaderInterface;
use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Exceptions\ConfigurationException;
use Symfony\Component\Yaml\Yaml;

final class CriteriaLoader implements CriteriaLoaderInterface
{
    private const DEFAULT_DIRECTORY = '.laraforge/criteria';

    public function __construct(
        private readonly LaraForgeInterface $laraforge,
    ) {}

    public function load(string $path): AcceptanceCriteria
    {
        if (! file_exists($path)) {
            throw new ConfigurationException("Criteria file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new ConfigurationException("Failed to read criteria file: {$path}");
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $data = match ($extension) {
            'yaml', 'yml' => $this->parseYaml($content, $path),
            'json' => $this->parseJson($content, $path),
            default => throw new ConfigurationException("Unsupported criteria format: {$extension}"),
        };

        $this->validateData($data, $path);

        return AcceptanceCriteria::fromArray($data);
    }

    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function find(string $feature, string $directory): ?string
    {
        $possibleNames = [
            "{$feature}.yaml",
            "{$feature}.yml",
            "{$feature}.json",
        ];

        foreach ($possibleNames as $name) {
            $path = rtrim($directory, '/').'/'.$name;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function validate(AcceptanceCriteria $criteria, string $testPath): ValidationResult
    {
        if ($criteria->isEmpty()) {
            return ValidationResult::fullyCovered($criteria);
        }

        $testContent = $this->getTestContent($testPath);
        if ($testContent === '') {
            return ValidationResult::noCoverage($criteria);
        }

        $coveredIds = [];
        $missingIds = [];

        foreach ($criteria->all() as $criterion) {
            if ($this->criterionIsCovered($criterion, $testContent)) {
                $coveredIds[] = $criterion->id;
            } else {
                $missingIds[] = $criterion->id;
            }
        }

        return new ValidationResult($coveredIds, $missingIds, $criteria);
    }

    public function defaultDirectory(): string
    {
        $configDirectory = $this->laraforge->config()->get('criteria.directory');

        if (is_string($configDirectory) && $configDirectory !== '') {
            return $this->laraforge->workingDirectory().'/'.ltrim($configDirectory, '/');
        }

        return $this->laraforge->workingDirectory().'/'.self::DEFAULT_DIRECTORY;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $content, string $path): array
    {
        try {
            $data = Yaml::parse($content);
        } catch (\Exception $e) {
            throw new ConfigurationException("Invalid YAML in criteria file: {$path}");
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(string $content, string $path): array
    {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConfigurationException("Invalid JSON in criteria file: {$path}");
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data, string $path): void
    {
        if (! isset($data['feature']) || ! is_string($data['feature'])) {
            throw new ConfigurationException("Criteria file missing 'feature' field: {$path}");
        }

        if (! isset($data['criteria']) || ! is_array($data['criteria'])) {
            throw new ConfigurationException("Criteria file missing 'criteria' array: {$path}");
        }

        foreach ($data['criteria'] as $index => $criterion) {
            if (! is_array($criterion)) {
                throw new ConfigurationException("Invalid criterion at index {$index}: {$path}");
            }

            if (! isset($criterion['id']) || ! is_string($criterion['id'])) {
                throw new ConfigurationException("Criterion missing 'id' at index {$index}: {$path}");
            }

            if (! isset($criterion['description']) || ! is_string($criterion['description'])) {
                throw new ConfigurationException("Criterion missing 'description' at index {$index}: {$path}");
            }
        }
    }

    private function getTestContent(string $testPath): string
    {
        if (is_file($testPath)) {
            $content = file_get_contents($testPath);

            return $content !== false ? $content : '';
        }

        if (is_dir($testPath)) {
            $content = '';
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($testPath),
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $fileContent = file_get_contents($file->getPathname());
                    if ($fileContent !== false) {
                        $content .= $fileContent."\n";
                    }
                }
            }

            return $content;
        }

        return '';
    }

    private function criterionIsCovered(AcceptanceCriterion $criterion, string $testContent): bool
    {
        // Check if the criterion ID appears in a docblock or comment
        if (str_contains($testContent, $criterion->id)) {
            return true;
        }

        // Check if a test method name matches the criterion description
        $methodName = $criterion->toTestMethodName();
        if (str_contains($testContent, $methodName)) {
            return true;
        }

        // Check for Pest-style test label
        $testLabel = $criterion->toTestLabel();
        if (str_contains(strtolower($testContent), $testLabel)) {
            return true;
        }

        return false;
    }
}
