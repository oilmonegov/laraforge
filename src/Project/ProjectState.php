<?php

declare(strict_types=1);

namespace LaraForge\Project;

use LaraForge\Project\Contracts\FeatureInterface;
use LaraForge\Project\Contracts\ProjectStateInterface;
use LaraForge\Worktree\WorktreeManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ProjectState implements ProjectStateInterface
{
    private Filesystem $filesystem;

    /**
     * @var array<string, Feature>
     */
    private array $features = [];

    /**
     * @var array<array{id: string, title: string, status: string, priority?: int}>
     */
    private array $backlog = [];

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    private ?string $currentFeatureId = null;

    private ?WorktreeManager $worktreeManager = null;

    public function __construct(
        private readonly string $name,
        private readonly string $version,
        private readonly string $rootPath,
        private readonly string $statePath,
    ) {
        $this->filesystem = new Filesystem;
    }

    public static function initialize(string $rootPath, string $name, string $version = '1.0.0'): self
    {
        $laraforgeDir = $rootPath.'/.laraforge';
        $statePath = $laraforgeDir.'/project.yaml';

        $state = new self($name, $version, $rootPath, $statePath);

        // Create directory structure
        $filesystem = new Filesystem;
        $dirs = [
            $laraforgeDir,
            $laraforgeDir.'/docs',
            $laraforgeDir.'/docs/prd',
            $laraforgeDir.'/docs/frd',
            $laraforgeDir.'/docs/design',
            $laraforgeDir.'/docs/tests',
            $laraforgeDir.'/worktrees',
        ];

        foreach ($dirs as $dir) {
            if (! $filesystem->exists($dir)) {
                $filesystem->mkdir($dir, 0755);
            }
        }

        $state->save();

        return $state;
    }

    public static function load(string $rootPath): ?self
    {
        $statePath = $rootPath.'/.laraforge/project.yaml';
        $filesystem = new Filesystem;

        if (! $filesystem->exists($statePath)) {
            return null;
        }

        $data = Yaml::parseFile($statePath);
        if (! is_array($data)) {
            return null;
        }

        $state = new self(
            name: $data['project']['name'] ?? 'Unknown',
            version: $data['project']['version'] ?? '1.0.0',
            rootPath: $rootPath,
            statePath: $statePath,
        );

        $state->loadFromData($data);

        return $state;
    }

    public function setWorktreeManager(WorktreeManager $manager): void
    {
        $this->worktreeManager = $manager;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function features(): array
    {
        return array_values($this->features);
    }

    public function feature(string $id): ?FeatureInterface
    {
        return $this->features[$id] ?? null;
    }

    public function currentFeature(): ?FeatureInterface
    {
        if (! $this->currentFeatureId) {
            return null;
        }

        return $this->features[$this->currentFeatureId] ?? null;
    }

    public function setCurrentFeature(string $featureId): void
    {
        if (isset($this->features[$featureId])) {
            $this->currentFeatureId = $featureId;
            $this->save();
        }
    }

    public function addFeature(FeatureInterface $feature): void
    {
        if ($feature instanceof Feature) {
            $this->features[$feature->id()] = $feature;
            $this->save();
        }
    }

    public function updateFeature(FeatureInterface $feature): void
    {
        if ($feature instanceof Feature && isset($this->features[$feature->id()])) {
            $this->features[$feature->id()] = $feature;
            $this->save();
        }
    }

    public function removeFeature(string $featureId): void
    {
        unset($this->features[$featureId]);
        if ($this->currentFeatureId === $featureId) {
            $this->currentFeatureId = null;
        }
        $this->save();
    }

    public function featuresByStatus(string $status): array
    {
        return array_values(array_filter(
            $this->features,
            fn (Feature $f) => $f->status() === $status
        ));
    }

    public function backlog(): array
    {
        return $this->backlog;
    }

    public function addToBacklog(string $id, string $title, int $priority = 3): void
    {
        $this->backlog[] = [
            'id' => $id,
            'title' => $title,
            'status' => 'pending',
            'priority' => $priority,
        ];
        $this->save();
    }

    public function removeFromBacklog(string $id): void
    {
        $this->backlog = array_values(array_filter(
            $this->backlog,
            fn (array $item) => $item['id'] !== $id
        ));
        $this->save();
    }

    public function promoteFromBacklog(string $id): ?Feature
    {
        foreach ($this->backlog as $i => $item) {
            if ($item['id'] === $id) {
                $feature = Feature::create($item['title'], '', $item['priority'] ?? 3);
                $this->addFeature($feature);
                unset($this->backlog[$i]);
                $this->backlog = array_values($this->backlog);
                $this->save();

                return $feature;
            }
        }

        return null;
    }

    public function activeSessions(): array
    {
        if (! $this->worktreeManager) {
            return [];
        }

        return $this->worktreeManager->activeSessions();
    }

    public function config(): array
    {
        return $this->config;
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (! is_array($value) || ! isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function setConfig(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (! isset($config[$k]) || ! is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }

        $this->save();
    }

    public function save(): void
    {
        $data = $this->toArray();
        $yaml = Yaml::dump($data, 10, 2);

        $dir = dirname($this->statePath);
        if (! $this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir, 0755);
        }

        $this->filesystem->dumpFile($this->statePath, $yaml);
    }

    public function reload(): void
    {
        if (! $this->filesystem->exists($this->statePath)) {
            return;
        }

        $data = Yaml::parseFile($this->statePath);
        if (is_array($data)) {
            $this->loadFromData($data);
        }
    }

    public function statePath(): string
    {
        return $this->statePath;
    }

    public function toArray(): array
    {
        return [
            'project' => [
                'name' => $this->name,
                'version' => $this->version,
            ],
            'current_feature' => $this->currentFeatureId,
            'features' => array_map(
                fn (Feature $f) => $f->toArray(),
                $this->features
            ),
            'backlog' => $this->backlog,
            'config' => $this->config,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function loadFromData(array $data): void
    {
        $this->currentFeatureId = $data['current_feature'] ?? null;

        if (isset($data['features'])) {
            foreach ($data['features'] as $featureData) {
                $feature = Feature::fromArray($featureData);
                $this->features[$feature->id()] = $feature;
            }
        }

        $this->backlog = $data['backlog'] ?? [];
        $this->config = $data['config'] ?? [];
    }
}
