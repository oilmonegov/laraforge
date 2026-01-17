<?php

declare(strict_types=1);

use LaraForge\Config\ConfigLoader;
use LaraForge\Exceptions\ConfigurationException;

describe('ConfigLoader', function () {
    it('can load yaml config', function () {
        $tempDir = createTempDirectory();
        $configPath = $tempDir.'/config.yaml';

        file_put_contents($configPath, <<<'YAML'
project:
  name: test-project
  version: 1.0.0
YAML);

        $loader = new ConfigLoader;
        $config = $loader->load($configPath);

        expect($config)->toBe([
            'project' => [
                'name' => 'test-project',
                'version' => '1.0.0',
            ],
        ]);
    });

    it('can load json config', function () {
        $tempDir = createTempDirectory();
        $configPath = $tempDir.'/config.json';

        file_put_contents($configPath, json_encode([
            'project' => [
                'name' => 'test-project',
            ],
        ]));

        $loader = new ConfigLoader;
        $config = $loader->load($configPath);

        expect($config['project']['name'])->toBe('test-project');
    });

    it('throws exception for non-existent file', function () {
        $loader = new ConfigLoader;

        expect(fn () => $loader->load('/non/existent/file.yaml'))
            ->toThrow(ConfigurationException::class);
    });

    it('throws exception for unsupported format', function () {
        $tempDir = createTempDirectory();
        $configPath = $tempDir.'/config.txt';
        file_put_contents($configPath, 'some text');

        $loader = new ConfigLoader;

        expect(fn () => $loader->load($configPath))
            ->toThrow(ConfigurationException::class, 'Unsupported configuration format');
    });

    it('can get nested config with dot notation', function () {
        $loader = new ConfigLoader;
        $loader->merge([
            'project' => [
                'name' => 'test',
                'nested' => [
                    'value' => 'deep',
                ],
            ],
        ]);

        expect($loader->get('project.name'))->toBe('test');
        expect($loader->get('project.nested.value'))->toBe('deep');
    });

    it('returns default for non-existent key', function () {
        $loader = new ConfigLoader;

        expect($loader->get('non.existent', 'default'))->toBe('default');
    });

    it('can set config with dot notation', function () {
        $loader = new ConfigLoader;
        $loader->set('project.name', 'test');
        $loader->set('project.nested.value', 'deep');

        expect($loader->get('project.name'))->toBe('test');
        expect($loader->get('project.nested.value'))->toBe('deep');
    });

    it('can check if key exists', function () {
        $loader = new ConfigLoader;
        $loader->set('existing.key', 'value');

        expect($loader->has('existing.key'))->toBeTrue();
        expect($loader->has('non.existent'))->toBeFalse();
    });

    it('can get all config', function () {
        $loader = new ConfigLoader;
        $loader->merge(['key' => 'value']);

        expect($loader->all())->toBe(['key' => 'value']);
    });

    it('merges config recursively', function () {
        $loader = new ConfigLoader;
        $loader->merge([
            'project' => [
                'name' => 'original',
                'keep' => 'this',
            ],
        ]);

        $loader->merge([
            'project' => [
                'name' => 'overwritten',
                'new' => 'value',
            ],
        ]);

        expect($loader->get('project.name'))->toBe('overwritten');
        expect($loader->get('project.keep'))->toBe('this');
        expect($loader->get('project.new'))->toBe('value');
    });
});
