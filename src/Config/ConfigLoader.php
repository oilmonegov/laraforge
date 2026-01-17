<?php

declare(strict_types=1);

namespace LaraForge\Config;

use LaraForge\Contracts\ConfigLoaderInterface;
use LaraForge\Exceptions\ConfigurationException;
use Symfony\Component\Yaml\Yaml;

final class ConfigLoader implements ConfigLoaderInterface
{
    /** @var array<string, mixed> */
    private array $config = [];

    public function load(string $path): array
    {
        if (! file_exists($path)) {
            throw new ConfigurationException("Configuration file not found: {$path}");
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'yaml', 'yml' => $this->loadYaml($path),
            'json' => $this->loadJson($path),
            'php' => $this->loadPhp($path),
            default => throw new ConfigurationException("Unsupported configuration format: {$extension}"),
        };
    }

    private function loadYaml(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new ConfigurationException("Failed to read configuration file: {$path}");
        }

        $parsed = Yaml::parse($content);

        return is_array($parsed) ? $parsed : [];
    }

    private function loadJson(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new ConfigurationException("Failed to read configuration file: {$path}");
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConfigurationException("Invalid JSON in configuration file: {$path}");
        }

        return is_array($parsed) ? $parsed : [];
    }

    private function loadPhp(string $path): array
    {
        $parsed = require $path;

        return is_array($parsed) ? $parsed : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $config[$segment] = $value;
            } else {
                if (! isset($config[$segment]) || ! is_array($config[$segment])) {
                    $config[$segment] = [];
                }
                $config = &$config[$segment];
            }
        }
    }

    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function merge(array $config): void
    {
        $this->config = $this->mergeRecursive($this->config, $config);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
