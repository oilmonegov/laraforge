<?php

declare(strict_types=1);

namespace LaraForge\DesignSystem;

/**
 * Brand Guidelines
 *
 * Defines brand identity, colors, typography, and design tokens.
 * Ensures consistency across all generated frontend components.
 */
final class BrandGuidelines
{
    /**
     * @param  array<string, string>  $colors
     * @param  array<string, mixed>  $typography
     * @param  array<string, string>  $spacing
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $name,
        private readonly array $colors = [],
        private readonly array $typography = [],
        private readonly array $spacing = [],
        private readonly array $metadata = [],
    ) {}

    /**
     * Get the brand name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get all brand colors.
     *
     * @return array<string, string>
     */
    public function getColors(): array
    {
        return array_merge($this->getDefaultColors(), $this->colors);
    }

    /**
     * Get a specific color by key.
     */
    public function getColor(string $key): ?string
    {
        return $this->getColors()[$key] ?? null;
    }

    /**
     * Get typography settings.
     *
     * @return array<string, mixed>
     */
    public function getTypography(): array
    {
        return array_merge($this->getDefaultTypography(), $this->typography);
    }

    /**
     * Get spacing scale.
     *
     * @return array<int|string, string>
     */
    public function getSpacing(): array
    {
        return array_merge($this->getDefaultSpacing(), $this->spacing);
    }

    /**
     * Get brand metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Generate Tailwind CSS color configuration.
     *
     * @return array<string, array<int|string, string>>
     */
    public function getTailwindColors(): array
    {
        $colors = $this->getColors();

        return [
            'primary' => $this->generateColorScale($colors['primary'] ?? '#3B82F6'),
            'secondary' => $this->generateColorScale($colors['secondary'] ?? '#6B7280'),
            'accent' => $this->generateColorScale($colors['accent'] ?? '#8B5CF6'),
            'success' => $this->generateColorScale($colors['success'] ?? '#10B981'),
            'warning' => $this->generateColorScale($colors['warning'] ?? '#F59E0B'),
            'danger' => $this->generateColorScale($colors['danger'] ?? '#EF4444'),
            'info' => $this->generateColorScale($colors['info'] ?? '#3B82F6'),
        ];
    }

    /**
     * Generate CSS custom properties.
     *
     * @return array<string, string>
     */
    public function getCssVariables(): array
    {
        $variables = [];
        $colors = $this->getColors();
        $typography = $this->getTypography();
        $spacing = $this->getSpacing();

        // Color variables
        foreach ($colors as $name => $value) {
            $variables["--color-{$name}"] = $value;
        }

        // Typography variables
        $variables['--font-family-sans'] = $typography['fontFamily']['sans'] ?? 'Inter, system-ui, sans-serif';
        $variables['--font-family-serif'] = $typography['fontFamily']['serif'] ?? 'Georgia, serif';
        $variables['--font-family-mono'] = $typography['fontFamily']['mono'] ?? 'JetBrains Mono, monospace';

        // Font sizes
        foreach ($typography['fontSize'] ?? [] as $name => $size) {
            $variables["--font-size-{$name}"] = $size;
        }

        // Spacing variables
        foreach ($spacing as $name => $value) {
            $variables["--spacing-{$name}"] = $value;
        }

        return $variables;
    }

    /**
     * Get design tokens for component generation.
     *
     * @return array<string, mixed>
     */
    public function getDesignTokens(): array
    {
        return [
            'colors' => $this->getColors(),
            'typography' => $this->getTypography(),
            'spacing' => $this->getSpacing(),
            'borderRadius' => $this->getBorderRadius(),
            'shadows' => $this->getShadows(),
            'transitions' => $this->getTransitions(),
        ];
    }

    /**
     * Export as JSON for design tools.
     */
    public function toJson(): string
    {
        return json_encode([
            'name' => $this->name,
            'tokens' => $this->getDesignTokens(),
            'metadata' => $this->metadata,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /**
     * Export as Figma-compatible tokens.
     *
     * @return array<string, mixed>
     */
    public function toFigmaTokens(): array
    {
        return [
            'global' => [
                'colors' => $this->transformColorsForFigma(),
                'typography' => $this->transformTypographyForFigma(),
                'spacing' => $this->transformSpacingForFigma(),
            ],
        ];
    }

    /**
     * Get border radius scale.
     *
     * @return array<string, string>
     */
    public function getBorderRadius(): array
    {
        return [
            'none' => '0',
            'sm' => '0.125rem',
            'default' => '0.25rem',
            'md' => '0.375rem',
            'lg' => '0.5rem',
            'xl' => '0.75rem',
            '2xl' => '1rem',
            '3xl' => '1.5rem',
            'full' => '9999px',
        ];
    }

    /**
     * Get shadow scale.
     *
     * @return array<string, string>
     */
    public function getShadows(): array
    {
        return [
            'sm' => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            'default' => '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
            'md' => '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
            'lg' => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
            'xl' => '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
            '2xl' => '0 25px 50px -12px rgb(0 0 0 / 0.25)',
            'inner' => 'inset 0 2px 4px 0 rgb(0 0 0 / 0.05)',
            'none' => 'none',
        ];
    }

    /**
     * Get transition presets.
     *
     * @return array<string, array<string, string>>
     */
    public function getTransitions(): array
    {
        return [
            'fast' => [
                'duration' => '150ms',
                'timing' => 'cubic-bezier(0.4, 0, 0.2, 1)',
            ],
            'default' => [
                'duration' => '200ms',
                'timing' => 'cubic-bezier(0.4, 0, 0.2, 1)',
            ],
            'slow' => [
                'duration' => '300ms',
                'timing' => 'cubic-bezier(0.4, 0, 0.2, 1)',
            ],
            'bounce' => [
                'duration' => '500ms',
                'timing' => 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
            ],
        ];
    }

    /**
     * Create from a project path by detecting existing brand assets.
     */
    public static function fromProject(string $projectPath): self
    {
        $detector = new ThemeDetector($projectPath);
        $detected = $detector->detect();

        return new self(
            name: $detected['name'] ?? 'Project',
            colors: $detected['colors'] ?? [],
            typography: $detected['typography'] ?? [],
            spacing: $detected['spacing'] ?? [],
            metadata: $detected['metadata'] ?? [],
        );
    }

    /**
     * Create with default settings.
     */
    public static function defaults(string $name = 'Default'): self
    {
        return new self(name: $name);
    }

    /**
     * @return array<string, string>
     */
    private function getDefaultColors(): array
    {
        return [
            'primary' => '#3B82F6',
            'secondary' => '#6B7280',
            'accent' => '#8B5CF6',
            'success' => '#10B981',
            'warning' => '#F59E0B',
            'danger' => '#EF4444',
            'info' => '#3B82F6',
            'background' => '#FFFFFF',
            'foreground' => '#111827',
            'muted' => '#F3F4F6',
            'border' => '#E5E7EB',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultTypography(): array
    {
        return [
            'fontFamily' => [
                'sans' => 'Inter, system-ui, sans-serif',
                'serif' => 'Georgia, Cambria, serif',
                'mono' => 'JetBrains Mono, Menlo, monospace',
            ],
            'fontSize' => [
                'xs' => '0.75rem',
                'sm' => '0.875rem',
                'base' => '1rem',
                'lg' => '1.125rem',
                'xl' => '1.25rem',
                '2xl' => '1.5rem',
                '3xl' => '1.875rem',
                '4xl' => '2.25rem',
                '5xl' => '3rem',
            ],
            'fontWeight' => [
                'normal' => '400',
                'medium' => '500',
                'semibold' => '600',
                'bold' => '700',
            ],
            'lineHeight' => [
                'tight' => '1.25',
                'normal' => '1.5',
                'relaxed' => '1.75',
            ],
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function getDefaultSpacing(): array
    {
        return [
            '0' => '0',
            '1' => '0.25rem',
            '2' => '0.5rem',
            '3' => '0.75rem',
            '4' => '1rem',
            '5' => '1.25rem',
            '6' => '1.5rem',
            '8' => '2rem',
            '10' => '2.5rem',
            '12' => '3rem',
            '16' => '4rem',
            '20' => '5rem',
            '24' => '6rem',
        ];
    }

    /**
     * Generate a color scale from a base color.
     *
     * @return array<int|string, string>
     */
    private function generateColorScale(string $baseColor): array
    {
        // In a real implementation, this would use color manipulation
        // For now, return the base color at different opacities
        return [
            '50' => $baseColor.'0D',
            '100' => $baseColor.'1A',
            '200' => $baseColor.'33',
            '300' => $baseColor.'4D',
            '400' => $baseColor.'80',
            '500' => $baseColor,
            '600' => $baseColor.'CC',
            '700' => $baseColor.'B3',
            '800' => $baseColor.'99',
            '900' => $baseColor.'80',
            '950' => $baseColor.'66',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function transformColorsForFigma(): array
    {
        $result = [];
        foreach ($this->getColors() as $name => $value) {
            $result[$name] = [
                'value' => $value,
                'type' => 'color',
            ];
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function transformTypographyForFigma(): array
    {
        $typography = $this->getTypography();
        $result = [];

        foreach ($typography['fontSize'] ?? [] as $name => $size) {
            $result["font-size-{$name}"] = [
                'value' => $size,
                'type' => 'fontSizes',
            ];
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function transformSpacingForFigma(): array
    {
        $result = [];
        foreach ($this->getSpacing() as $name => $value) {
            $result["spacing-{$name}"] = [
                'value' => $value,
                'type' => 'spacing',
            ];
        }

        return $result;
    }
}
