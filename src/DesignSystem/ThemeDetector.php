<?php

declare(strict_types=1);

namespace LaraForge\DesignSystem;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Theme Detector
 *
 * Detects premium themes and existing design systems in a project.
 * Supports: Tailwind UI, shadcn/ui, Headless UI, Preline, Flowbite, etc.
 */
final class ThemeDetector
{
    private Filesystem $filesystem;

    /**
     * Known premium theme signatures.
     *
     * @var array<string, array<string, mixed>>
     */
    private const THEME_SIGNATURES = [
        'tailwindui' => [
            'files' => ['tailwind.config.js', 'tailwind.config.ts'],
            'patterns' => ['@tailwindcss/forms', '@tailwindcss/typography', '@tailwindcss/aspect-ratio'],
            'directories' => ['resources/js/Components', 'resources/views/components'],
        ],
        'shadcn' => [
            'files' => ['components.json'],
            'patterns' => ['@radix-ui', 'class-variance-authority', 'clsx', 'tailwind-merge'],
            'directories' => ['resources/js/components/ui', 'components/ui'],
        ],
        'headlessui' => [
            'patterns' => ['@headlessui/react', '@headlessui/vue'],
        ],
        'preline' => [
            'patterns' => ['preline'],
            'files' => ['node_modules/preline'],
        ],
        'flowbite' => [
            'patterns' => ['flowbite', 'flowbite-react', 'flowbite-vue'],
        ],
        'daisyui' => [
            'patterns' => ['daisyui'],
        ],
        'mantine' => [
            'patterns' => ['@mantine/core', '@mantine/hooks'],
        ],
    ];

    public function __construct(
        private readonly string $projectPath,
    ) {
        $this->filesystem = new Filesystem;
    }

    /**
     * Detect theme and brand configuration from project.
     *
     * @return array<string, mixed>
     */
    public function detect(): array
    {
        return [
            'name' => $this->detectProjectName(),
            'theme' => $this->detectTheme(),
            'colors' => $this->detectColors(),
            'typography' => $this->detectTypography(),
            'spacing' => $this->detectSpacing(),
            'components' => $this->detectComponentLibrary(),
            'metadata' => $this->getMetadata(),
        ];
    }

    /**
     * Detect the primary UI theme/library being used.
     *
     * @return array<string, mixed>
     */
    public function detectTheme(): array
    {
        $packageJson = $this->getPackageJson();
        $composerJson = $this->getComposerJson();

        $dependencies = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        );

        $detected = [];

        foreach (self::THEME_SIGNATURES as $theme => $signatures) {
            $score = 0;

            // Check npm patterns
            foreach ($signatures['patterns'] as $pattern) {
                if (isset($dependencies[$pattern])) {
                    $score += 2;
                }
            }

            // Check files
            foreach ($signatures['files'] ?? [] as $file) {
                if ($this->filesystem->exists($this->projectPath.'/'.$file)) {
                    $score += 1;
                }
            }

            // Check directories
            foreach ($signatures['directories'] ?? [] as $dir) {
                if ($this->filesystem->exists($this->projectPath.'/'.$dir)) {
                    $score += 1;
                }
            }

            if ($score > 0) {
                $detected[$theme] = $score;
            }
        }

        arsort($detected);

        $primary = array_key_first($detected);

        return [
            'primary' => $primary,
            'all' => $detected,
            'hasTailwind' => $this->hasTailwind(),
            'hasPostcss' => $this->hasPostCss(),
        ];
    }

    /**
     * Detect colors from Tailwind config or CSS variables.
     *
     * @return array<string, string>
     */
    public function detectColors(): array
    {
        $colors = [];

        // Try to read from tailwind.config.js
        $tailwindConfig = $this->getTailwindConfig();
        if (isset($tailwindConfig['theme']['extend']['colors'])) {
            $colors = array_merge($colors, $this->flattenColors($tailwindConfig['theme']['extend']['colors']));
        }

        // Try to read from CSS custom properties
        $cssColors = $this->detectCssVariableColors();
        $colors = array_merge($colors, $cssColors);

        // Try to read from shadcn components.json
        $shadcnConfig = $this->getShadcnConfig();
        if ($shadcnConfig !== []) {
            $colors = array_merge($colors, $this->extractShadcnColors($shadcnConfig));
        }

        return $colors;
    }

    /**
     * Detect typography settings.
     *
     * @return array<string, mixed>
     */
    public function detectTypography(): array
    {
        $tailwindConfig = $this->getTailwindConfig();

        return [
            'fontFamily' => $tailwindConfig['theme']['extend']['fontFamily'] ?? [],
            'fontSize' => $tailwindConfig['theme']['extend']['fontSize'] ?? [],
        ];
    }

    /**
     * Detect spacing configuration.
     *
     * @return array<string, string>
     */
    public function detectSpacing(): array
    {
        $tailwindConfig = $this->getTailwindConfig();

        return $tailwindConfig['theme']['extend']['spacing'] ?? [];
    }

    /**
     * Detect component library structure.
     *
     * @return array<string, mixed>
     */
    public function detectComponentLibrary(): array
    {
        $components = [];

        // Check common component directories
        $componentPaths = [
            'resources/js/Components' => 'vue/react',
            'resources/js/components' => 'vue/react',
            'resources/views/components' => 'blade',
            'resources/views/livewire' => 'livewire',
            'app/Livewire' => 'livewire',
            'app/View/Components' => 'blade-class',
        ];

        foreach ($componentPaths as $path => $type) {
            $fullPath = $this->projectPath.'/'.$path;
            if ($this->filesystem->exists($fullPath)) {
                $components[$type] = [
                    'path' => $path,
                    'files' => $this->countFilesInDirectory($fullPath),
                ];
            }
        }

        return $components;
    }

    /**
     * Detect existing premium themes in themes directory.
     *
     * @return array<string, array<string, mixed>>
     */
    public function detectPremiumThemes(): array
    {
        $themes = [];

        // Check common theme directories
        $themeDirs = [
            'resources/themes',
            'resources/js/themes',
            'themes',
            'frontend/themes',
        ];

        foreach ($themeDirs as $dir) {
            $fullPath = $this->projectPath.'/'.$dir;
            if ($this->filesystem->exists($fullPath)) {
                $themes = array_merge($themes, $this->scanThemeDirectory($fullPath));
            }
        }

        return $themes;
    }

    /**
     * Check if project uses Tailwind CSS.
     */
    public function hasTailwind(): bool
    {
        $packageJson = $this->getPackageJson();
        $dependencies = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        );

        return isset($dependencies['tailwindcss']);
    }

    /**
     * Check if project uses PostCSS.
     */
    public function hasPostCss(): bool
    {
        return $this->filesystem->exists($this->projectPath.'/postcss.config.js')
            || $this->filesystem->exists($this->projectPath.'/postcss.config.cjs');
    }

    /**
     * Get metadata about detected setup.
     *
     * @return array<string, mixed>
     */
    private function getMetadata(): array
    {
        $packageJson = $this->getPackageJson();

        return [
            'nodeVersion' => $packageJson['engines']['node'] ?? null,
            'buildTool' => $this->detectBuildTool(),
            'cssFramework' => $this->detectCssFramework(),
        ];
    }

    private function detectProjectName(): string
    {
        $composerJson = $this->getComposerJson();
        $packageJson = $this->getPackageJson();

        return $packageJson['name']
            ?? $composerJson['name']
            ?? basename($this->projectPath);
    }

    private function detectBuildTool(): string
    {
        if ($this->filesystem->exists($this->projectPath.'/vite.config.js')
            || $this->filesystem->exists($this->projectPath.'/vite.config.ts')) {
            return 'vite';
        }

        if ($this->filesystem->exists($this->projectPath.'/webpack.mix.js')) {
            return 'laravel-mix';
        }

        if ($this->filesystem->exists($this->projectPath.'/webpack.config.js')) {
            return 'webpack';
        }

        return 'unknown';
    }

    private function detectCssFramework(): string
    {
        if ($this->hasTailwind()) {
            return 'tailwind';
        }

        $packageJson = $this->getPackageJson();
        $dependencies = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? [],
        );

        if (isset($dependencies['bootstrap'])) {
            return 'bootstrap';
        }

        if (isset($dependencies['bulma'])) {
            return 'bulma';
        }

        return 'custom';
    }

    /**
     * @return array<string, mixed>
     */
    private function getPackageJson(): array
    {
        $path = $this->projectPath.'/package.json';

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getComposerJson(): array
    {
        $path = $this->projectPath.'/composer.json';

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getTailwindConfig(): array
    {
        // In a real implementation, we'd parse the JS config
        // For now, return empty array
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getShadcnConfig(): array
    {
        $path = $this->projectPath.'/components.json';

        if (! $this->filesystem->exists($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?? [];
    }

    /**
     * @param  array<string, mixed>  $colors
     * @return array<string, string>
     */
    private function flattenColors(array $colors, string $prefix = ''): array
    {
        $result = [];

        foreach ($colors as $key => $value) {
            $newKey = $prefix ? "{$prefix}-{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenColors($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function detectCssVariableColors(): array
    {
        // Check for CSS files with custom properties
        $cssFiles = [
            'resources/css/app.css',
            'resources/css/variables.css',
            'public/css/app.css',
        ];

        foreach ($cssFiles as $file) {
            $fullPath = $this->projectPath.'/'.$file;
            if ($this->filesystem->exists($fullPath)) {
                return $this->parseCssVariables((string) file_get_contents($fullPath));
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function parseCssVariables(string $css): array
    {
        $colors = [];

        // Match CSS custom properties that look like colors
        if (preg_match_all('/--([a-z-]+):\s*(#[a-fA-F0-9]{3,8}|rgb[a]?\([^)]+\)|hsl[a]?\([^)]+\))/i', $css, $matches)) {
            foreach ($matches[1] as $i => $name) {
                $colors[$name] = $matches[2][$i];
            }
        }

        return $colors;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function extractShadcnColors(array $config): array
    {
        // shadcn uses CSS variables, try to extract from style config
        return $config['cssVariables'] ?? [];
    }

    private function countFilesInDirectory(string $path): int
    {
        if (! $this->filesystem->exists($path)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function scanThemeDirectory(string $path): array
    {
        $themes = [];

        if (! is_dir($path)) {
            return $themes;
        }

        $items = scandir($path);
        if ($items === false) {
            return $themes;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $themePath = $path.'/'.$item;
            if (is_dir($themePath)) {
                $themes[$item] = [
                    'path' => $themePath,
                    'hasConfig' => $this->filesystem->exists($themePath.'/theme.json'),
                    'files' => $this->countFilesInDirectory($themePath),
                ];
            }
        }

        return $themes;
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $path): self
    {
        return new self($path);
    }
}
