<?php

declare(strict_types=1);

namespace LaraForge;

use LaraForge\Config\ConfigLoader;
use LaraForge\Contracts\AdapterInterface;
use LaraForge\Contracts\ConfigLoaderInterface;
use LaraForge\Contracts\CriteriaLoaderInterface;
use LaraForge\Contracts\GeneratorInterface;
use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Contracts\PluginInterface;
use LaraForge\Contracts\TemplateEngineInterface;
use LaraForge\Criteria\CriteriaLoader;
use LaraForge\Generators\ApiResourceGenerator;
use LaraForge\Generators\FeatureTestGenerator;
use LaraForge\Generators\GitHooksGenerator;
use LaraForge\Generators\ManagerGenerator;
use LaraForge\Generators\PolicyGenerator;
use LaraForge\Templates\TemplateEngine;

final class LaraForge implements LaraForgeInterface
{
    public const VERSION = '1.0.0';

    private string $workingDirectory;

    private ConfigLoaderInterface $config;

    private TemplateEngineInterface $templates;

    private CriteriaLoaderInterface $criteriaLoader;

    /** @var array<string, AdapterInterface> */
    private array $adapters = [];

    /** @var array<string, PluginInterface> */
    private array $plugins = [];

    /** @var array<string, GeneratorInterface> */
    private array $generators = [];

    private ?AdapterInterface $activeAdapter = null;

    private bool $booted = false;

    public function __construct(?string $workingDirectory = null)
    {
        $this->workingDirectory = $workingDirectory ?? getcwd() ?: '.';
        $this->config = new ConfigLoader;
        $this->templates = new TemplateEngine;
        $this->criteriaLoader = new CriteriaLoader($this);

        $this->initialize();
    }

    private function initialize(): void
    {
        // Add core templates path
        $this->templates->addPath(__DIR__.'/../resources/templates', 0);

        // Add override path (highest priority)
        if ($this->hasOverrides()) {
            $this->templates->addPath($this->overridePath().'/templates', 100);
        }

        // Load core configuration
        $coreConfigPath = __DIR__.'/../resources/config/laraforge.yaml';
        if (file_exists($coreConfigPath)) {
            $this->config->merge($this->config->load($coreConfigPath));
        }

        // Load project configuration
        $this->loadProjectConfig();

        // Register core generators
        $this->registerCoreGenerators();
    }

    private function registerCoreGenerators(): void
    {
        $this->registerGenerator('git-hooks', new GitHooksGenerator($this));
        $this->registerGenerator('api-resource', new ApiResourceGenerator($this));
        $this->registerGenerator('feature-test', new FeatureTestGenerator($this));
        $this->registerGenerator('policy', new PolicyGenerator($this));
        $this->registerGenerator('manager', new ManagerGenerator($this));
    }

    private function loadProjectConfig(): void
    {
        $configFiles = [
            $this->workingDirectory.'/laraforge.yaml',
            $this->workingDirectory.'/laraforge.yml',
            $this->workingDirectory.'/.laraforge/config.yaml',
            $this->workingDirectory.'/.laraforge/config.yml',
        ];

        foreach ($configFiles as $configFile) {
            if (file_exists($configFile)) {
                $this->config->merge($this->config->load($configFile));
                break;
            }
        }
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function workingDirectory(): string
    {
        return $this->workingDirectory;
    }

    public function setWorkingDirectory(string $path): void
    {
        $this->workingDirectory = $path;
        $this->activeAdapter = null;
        $this->loadProjectConfig();
    }

    public function config(): ConfigLoaderInterface
    {
        return $this->config;
    }

    public function templates(): TemplateEngineInterface
    {
        return $this->templates;
    }

    public function criteriaLoader(): CriteriaLoaderInterface
    {
        return $this->criteriaLoader;
    }

    public function registerAdapter(AdapterInterface $adapter): void
    {
        $this->adapters[$adapter->identifier()] = $adapter;

        // Add adapter templates path
        $this->templates->addPath($adapter->templatesPath(), $adapter->priority());

        // Register adapter generators
        foreach ($adapter->generators() as $name => $generatorClass) {
            $this->registerGenerator($name, new $generatorClass($this));
        }

        // Reset active adapter
        $this->activeAdapter = null;
    }

    public function adapter(): ?AdapterInterface
    {
        if ($this->activeAdapter !== null) {
            return $this->activeAdapter;
        }

        // Find applicable adapter with highest priority
        $applicable = [];
        foreach ($this->adapters as $adapter) {
            if ($adapter->isApplicable($this->workingDirectory)) {
                $applicable[] = $adapter;
            }
        }

        if (empty($applicable)) {
            return null;
        }

        // Sort by priority (descending)
        usort($applicable, fn ($a, $b) => $b->priority() <=> $a->priority());

        $this->activeAdapter = $applicable[0];
        $this->activeAdapter->bootstrap($this->workingDirectory);

        return $this->activeAdapter;
    }

    public function adapters(): array
    {
        return $this->adapters;
    }

    public function registerPlugin(PluginInterface $plugin): void
    {
        $this->plugins[$plugin->identifier()] = $plugin;
        $plugin->register($this);

        // Add plugin templates path
        if ($plugin->templatesPath() !== null) {
            $this->templates->addPath($plugin->templatesPath(), 50);
        }

        // Register plugin generators
        foreach ($plugin->generators() as $name => $generatorClass) {
            $this->registerGenerator($name, new $generatorClass($this));
        }
    }

    public function plugins(): array
    {
        return $this->plugins;
    }

    public function registerGenerator(string $name, GeneratorInterface $generator): void
    {
        $this->generators[$name] = $generator;
    }

    public function generator(string $name): ?GeneratorInterface
    {
        return $this->generators[$name] ?? null;
    }

    public function generators(): array
    {
        return $this->generators;
    }

    public function overridePath(): string
    {
        return $this->workingDirectory.'/.laraforge';
    }

    public function hasOverrides(): bool
    {
        return is_dir($this->overridePath());
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Boot all plugins
        foreach ($this->plugins as $plugin) {
            $plugin->boot($this);
        }

        // Bootstrap active adapter
        $this->adapter();

        $this->booted = true;
    }
}
