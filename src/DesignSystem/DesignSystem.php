<?php

declare(strict_types=1);

namespace LaraForge\DesignSystem;

/**
 * Design System
 *
 * Central orchestrator for the design system, bringing together
 * brand guidelines, component library, theme detection, and storage.
 */
final class DesignSystem
{
    private BrandGuidelines $brand;

    private ComponentLibrary $components;

    private ThemeDetector $themeDetector;

    private StorageConfiguration $storage;

    private ServiceResilience $resilience;

    public function __construct(
        private readonly string $projectPath,
        private readonly string $stack = 'blade',
    ) {
        $this->themeDetector = new ThemeDetector($projectPath);
        $this->brand = BrandGuidelines::fromProject($projectPath);
        $this->components = new ComponentLibrary($this->brand, $stack);
        $this->storage = StorageConfiguration::create();
        $this->resilience = ServiceResilience::create();
    }

    /**
     * Get the brand guidelines.
     */
    public function getBrand(): BrandGuidelines
    {
        return $this->brand;
    }

    /**
     * Get the component library.
     */
    public function getComponents(): ComponentLibrary
    {
        return $this->components;
    }

    /**
     * Get the theme detector.
     */
    public function getThemeDetector(): ThemeDetector
    {
        return $this->themeDetector;
    }

    /**
     * Get the storage configuration.
     */
    public function getStorage(): StorageConfiguration
    {
        return $this->storage;
    }

    /**
     * Get the service resilience patterns.
     */
    public function getResilience(): ServiceResilience
    {
        return $this->resilience;
    }

    /**
     * Detect the current design system configuration.
     *
     * @return array<string, mixed>
     */
    public function detect(): array
    {
        return [
            'theme' => $this->themeDetector->detectTheme(),
            'brand' => [
                'name' => $this->brand->getName(),
                'colors' => $this->brand->getColors(),
                'typography' => $this->brand->getTypography(),
            ],
            'components' => $this->themeDetector->detectComponentLibrary(),
            'stack' => $this->stack,
            'hasTailwind' => $this->themeDetector->hasTailwind(),
        ];
    }

    /**
     * Get AI context for design system.
     *
     * @return array<string, mixed>
     */
    public function getAiContext(): array
    {
        $detected = $this->detect();

        return [
            'design_system' => [
                'brand' => $this->brand->getName(),
                'css_framework' => $detected['hasTailwind'] ? 'tailwind' : 'custom',
                'component_library' => $detected['theme']['primary'] ?? 'custom',
                'stack' => $this->stack,
            ],
            'styling' => [
                'colors' => $this->brand->getColors(),
                'spacing' => $this->brand->getSpacing(),
                'border_radius' => $this->brand->getBorderRadius(),
            ],
            'patterns' => [
                'Use design tokens for all styling',
                'Follow component composition patterns',
                'Ensure accessibility (WCAG 2.1 AA)',
                'Use semantic HTML elements',
                'Implement responsive design (mobile-first)',
            ],
            'components' => array_keys($this->components->getComponents()),
            'resilience' => [
                'Use circuit breakers for external services',
                'Implement retry with exponential backoff',
                'Provide graceful degradation',
                'Show loading states for async operations',
                'Handle offline scenarios',
            ],
        ];
    }

    /**
     * Generate Tailwind configuration.
     *
     * @return array<string, mixed>
     */
    public function generateTailwindConfig(): array
    {
        return [
            'theme' => [
                'extend' => [
                    'colors' => $this->brand->getTailwindColors(),
                    'spacing' => $this->brand->getSpacing(),
                    'borderRadius' => $this->brand->getBorderRadius(),
                    'boxShadow' => $this->brand->getShadows(),
                ],
            ],
        ];
    }

    /**
     * Generate CSS custom properties.
     */
    public function generateCssVariables(): string
    {
        $variables = $this->brand->getCssVariables();
        $lines = [':root {'];

        foreach ($variables as $name => $value) {
            $lines[] = "  {$name}: {$value};";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Get component stub for a specific component.
     */
    public function getComponentStub(string $component): string
    {
        return $this->components->getTemplate($component);
    }

    /**
     * Get component documentation.
     *
     * @return array<string, mixed>
     */
    public function getComponentDocumentation(string $component): array
    {
        return $this->components->getDocumentation($component);
    }

    /**
     * Get all available components grouped by category.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getComponentsByCategory(): array
    {
        $components = $this->components->getComponents();
        $grouped = [];

        foreach ($components as $name => $definition) {
            $category = $definition['category'] ?? 'other';
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$name] = $definition;
        }

        return $grouped;
    }

    /**
     * Validate a component implementation against the design system.
     *
     * @return array<string, mixed>
     */
    public function validateComponent(string $componentHtml): array
    {
        $issues = [];

        // Check for hardcoded colors
        if (preg_match('/(#[a-fA-F0-9]{3,8}|rgb\(|hsl\()/', $componentHtml)) {
            $issues[] = [
                'type' => 'warning',
                'message' => 'Hardcoded color values detected. Use design tokens instead.',
            ];
        }

        // Check for hardcoded spacing
        if (preg_match('/\d+px|\d+rem|\d+em/', $componentHtml)) {
            $issues[] = [
                'type' => 'info',
                'message' => 'Consider using spacing scale tokens for consistency.',
            ];
        }

        // Check for accessibility
        if (preg_match('/<img[^>]*(?!alt=)[^>]*>/', $componentHtml)) {
            $issues[] = [
                'type' => 'error',
                'message' => 'Images must have alt attributes for accessibility.',
            ];
        }

        // Check for interactive elements without focus styles
        if (preg_match('/<button|<a|<input/i', $componentHtml) && ! preg_match('/focus:/i', $componentHtml)) {
            $issues[] = [
                'type' => 'warning',
                'message' => 'Interactive elements should have focus styles.',
            ];
        }

        return [
            'valid' => array_filter($issues, fn ($i) => $i['type'] === 'error') === [],
            'issues' => $issues,
        ];
    }

    /**
     * Get storage configuration for file uploads.
     *
     * @return array<string, mixed>
     */
    public function getStorageConfig(): array
    {
        return [
            'providers' => $this->storage->getProviders(),
            'categories' => $this->storage->getCategories(),
        ];
    }

    /**
     * Get resilience patterns for a specific service type.
     *
     * @return array<string, mixed>
     */
    public function getResiliencePattern(string $serviceType): array
    {
        return match ($serviceType) {
            'api' => [
                'circuit_breaker' => $this->resilience->getCircuitBreakerPattern($serviceType),
                'retry' => $this->resilience->getRetryPattern(),
                'degradation' => $this->resilience->getDegradationStrategies(),
            ],
            'storage' => [
                'retry' => $this->resilience->getRetryPattern(),
                'fallback' => 'local',
            ],
            'notification' => [
                'retry' => $this->resilience->getRetryPattern(),
                'degradation' => ['queue_for_retry'],
            ],
            default => [
                'circuit_breaker' => $this->resilience->getCircuitBreakerPattern($serviceType),
                'retry' => $this->resilience->getRetryPattern(),
            ],
        };
    }

    /**
     * Get the project path.
     */
    public function getProjectPath(): string
    {
        return $this->projectPath;
    }

    /**
     * Export design system configuration.
     *
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'version' => '1.0.0',
            'brand' => [
                'name' => $this->brand->getName(),
                'tokens' => $this->brand->getDesignTokens(),
            ],
            'theme' => $this->themeDetector->detectTheme(),
            'components' => array_keys($this->components->getComponents()),
            'stack' => $this->stack,
            'storage' => [
                'categories' => array_keys($this->storage->getCategories()),
            ],
        ];
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $path, string $stack = 'blade'): self
    {
        return new self($path, $stack);
    }
}
