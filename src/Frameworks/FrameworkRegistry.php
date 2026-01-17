<?php

declare(strict_types=1);

namespace LaraForge\Frameworks;

use LaraForge\Core\Contracts\FrameworkAdapterInterface;
use LaraForge\Core\FrameworkDetector;

/**
 * Framework Registry
 *
 * Manages framework adapters and provides a unified interface
 * for framework-specific operations across PHP projects.
 */
final class FrameworkRegistry
{
    /**
     * @var array<string, FrameworkAdapterInterface>
     */
    private array $adapters = [];

    private ?FrameworkAdapterInterface $currentAdapter = null;

    public function __construct(
        private readonly string $projectPath,
    ) {
        $this->registerDefaultAdapters();
        $this->detectCurrentAdapter();
    }

    /**
     * Register a framework adapter.
     */
    public function register(FrameworkAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->identifier()] = $adapter;
    }

    /**
     * Get an adapter by identifier.
     */
    public function get(string $identifier): ?FrameworkAdapterInterface
    {
        return $this->adapters[$identifier] ?? null;
    }

    /**
     * Get the current (detected) framework adapter.
     */
    public function current(): FrameworkAdapterInterface
    {
        return $this->currentAdapter ?? $this->adapters['generic'];
    }

    /**
     * Get all registered adapters.
     *
     * @return array<string, FrameworkAdapterInterface>
     */
    public function all(): array
    {
        return $this->adapters;
    }

    /**
     * Get framework detection result.
     *
     * @return array{framework: string, version: ?string, confidence: float}
     */
    public function detect(): array
    {
        $detector = FrameworkDetector::fromPath($this->projectPath);
        $result = $detector->detect();

        return [
            'framework' => $result['framework'],
            'version' => $result['version'],
            'confidence' => $result['confidence'],
        ];
    }

    /**
     * Check if the project uses a specific framework.
     */
    public function isFramework(string $framework): bool
    {
        return $this->current()->identifier() === $framework;
    }

    /**
     * Get combined context for AI prompts.
     *
     * @return array<string, mixed>
     */
    public function getAiContext(): array
    {
        $adapter = $this->current();
        $context = $adapter->getAiContext();

        // Add framework-agnostic context
        $context['project'] = [
            'path' => $this->projectPath,
            'framework' => $adapter->identifier(),
            'version' => $adapter->version(),
        ];

        $context['structure'] = $adapter->getDirectoryStructure();
        $context['documentation'] = $adapter->getDocumentationUrls();

        return $context;
    }

    /**
     * Resolve a component path using the current framework's conventions.
     */
    public function resolvePath(string $component, string $name): string
    {
        return $this->current()->resolvePath($component, $name);
    }

    /**
     * Get security rules for the current framework.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSecurityRules(): array
    {
        return $this->current()->getSecurityRules();
    }

    /**
     * Get coding standards for the current framework.
     *
     * @return array<string, mixed>
     */
    public function getCodingStandards(): array
    {
        return $this->current()->getCodingStandards();
    }

    /**
     * Get testing conventions for the current framework.
     *
     * @return array<string, mixed>
     */
    public function getTestingConventions(): array
    {
        return $this->current()->getTestingConventions();
    }

    private function registerDefaultAdapters(): void
    {
        $detector = FrameworkDetector::fromPath($this->projectPath);

        // Register generic adapter first
        $this->register(new GenericPhpAdapter($detector));

        // Register Laravel adapter
        $this->register(new LaravelAdapter($detector));

        // Future: Register Symfony, Slim, etc.
    }

    private function detectCurrentAdapter(): void
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->isApplicable()) {
                $this->currentAdapter = $adapter;

                return;
            }
        }

        // Default to generic
        $this->currentAdapter = $this->adapters['generic'] ?? null;
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $path): self
    {
        return new self($path);
    }
}
