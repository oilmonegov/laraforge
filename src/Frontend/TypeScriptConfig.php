<?php

declare(strict_types=1);

namespace LaraForge\Frontend;

/**
 * TypeScript Configuration Helper
 *
 * Provides TypeScript configuration recommendations and setup helpers
 * for Laravel frontend projects using Vue, React, or Inertia.
 */
final class TypeScriptConfig
{
    public const FRAMEWORK_VUE = 'vue';

    public const FRAMEWORK_REACT = 'react';

    public const FRAMEWORK_INERTIA_VUE = 'inertia-vue';

    public const FRAMEWORK_INERTIA_REACT = 'inertia-react';

    public function __construct(
        private readonly string $framework = self::FRAMEWORK_VUE,
        private readonly bool $strictMode = true,
    ) {}

    /**
     * Get recommended tsconfig.json configuration.
     *
     * @return array<string, mixed>
     */
    public function getTsConfig(): array
    {
        $base = [
            'compilerOptions' => [
                'target' => 'ES2022',
                'module' => 'ESNext',
                'moduleResolution' => 'bundler',
                'strict' => $this->strictMode,
                'noEmit' => true,
                'esModuleInterop' => true,
                'skipLibCheck' => true,
                'forceConsistentCasingInFileNames' => true,
                'resolveJsonModule' => true,
                'isolatedModules' => true,
                'noUnusedLocals' => true,
                'noUnusedParameters' => true,
                'noFallthroughCasesInSwitch' => true,
                'baseUrl' => '.',
                'paths' => [
                    '@/*' => ['resources/js/*'],
                ],
            ],
            'include' => [
                'resources/js/**/*.ts',
                'resources/js/**/*.d.ts',
            ],
            'exclude' => [
                'node_modules',
                'vendor',
            ],
        ];

        return match ($this->framework) {
            self::FRAMEWORK_VUE, self::FRAMEWORK_INERTIA_VUE => $this->addVueConfig($base),
            self::FRAMEWORK_REACT, self::FRAMEWORK_INERTIA_REACT => $this->addReactConfig($base),
            default => $base,
        };
    }

    /**
     * Get recommended packages for TypeScript setup.
     *
     * @return array<string, array<string, string>>
     */
    public function getPackages(): array
    {
        $base = [
            'devDependencies' => [
                'typescript' => '^5.0',
                '@types/node' => '^20.0',
            ],
        ];

        $frameworkPackages = match ($this->framework) {
            self::FRAMEWORK_VUE => [
                'devDependencies' => [
                    'vue-tsc' => '^2.0',
                    '@vue/tsconfig' => '^0.5',
                ],
            ],
            self::FRAMEWORK_REACT => [
                'devDependencies' => [
                    '@types/react' => '^18.0',
                    '@types/react-dom' => '^18.0',
                ],
            ],
            self::FRAMEWORK_INERTIA_VUE => [
                'devDependencies' => [
                    'vue-tsc' => '^2.0',
                    '@vue/tsconfig' => '^0.5',
                    '@inertiajs/vue3' => '^1.0',
                ],
            ],
            self::FRAMEWORK_INERTIA_REACT => [
                'devDependencies' => [
                    '@types/react' => '^18.0',
                    '@types/react-dom' => '^18.0',
                    '@inertiajs/react' => '^1.0',
                ],
            ],
            default => [],
        };

        return array_merge_recursive($base, $frameworkPackages);
    }

    /**
     * Get ESLint configuration for TypeScript.
     *
     * @return array<string, mixed>
     */
    public function getEslintConfig(): array
    {
        $base = [
            'root' => true,
            'env' => [
                'browser' => true,
                'es2022' => true,
                'node' => true,
            ],
            'extends' => [
                'eslint:recommended',
            ],
            'parserOptions' => [
                'ecmaVersion' => 'latest',
                'sourceType' => 'module',
            ],
            'rules' => [
                'no-console' => ['warn', ['allow' => ['warn', 'error']]],
                'no-unused-vars' => 'off',
            ],
            'ignorePatterns' => [
                'node_modules/',
                'vendor/',
                'public/',
                '*.min.js',
            ],
        ];

        return match ($this->framework) {
            self::FRAMEWORK_VUE, self::FRAMEWORK_INERTIA_VUE => $this->addVueEslintConfig($base),
            self::FRAMEWORK_REACT, self::FRAMEWORK_INERTIA_REACT => $this->addReactEslintConfig($base),
            default => $base,
        };
    }

    /**
     * Get Prettier configuration.
     *
     * @return array<string, mixed>
     */
    public function getPrettierConfig(): array
    {
        return [
            'semi' => true,
            'singleQuote' => true,
            'tabWidth' => 2,
            'trailingComma' => 'es5',
            'printWidth' => 100,
            'bracketSpacing' => true,
            'arrowParens' => 'always',
            'endOfLine' => 'lf',
            'plugins' => match ($this->framework) {
                self::FRAMEWORK_VUE, self::FRAMEWORK_INERTIA_VUE => ['prettier-plugin-tailwindcss'],
                self::FRAMEWORK_REACT, self::FRAMEWORK_INERTIA_REACT => ['prettier-plugin-tailwindcss'],
                default => [],
            },
        ];
    }

    /**
     * Get Vite configuration for TypeScript.
     *
     * @return array<string, mixed>
     */
    public function getViteConfig(): array
    {
        return [
            'plugins' => match ($this->framework) {
                self::FRAMEWORK_VUE, self::FRAMEWORK_INERTIA_VUE => ['laravel', 'vue'],
                self::FRAMEWORK_REACT, self::FRAMEWORK_INERTIA_REACT => ['laravel', 'react'],
                default => ['laravel'],
            },
            'resolve' => [
                'alias' => [
                    '@' => '/resources/js',
                ],
            ],
        ];
    }

    /**
     * Get type declaration file content for Laravel.
     */
    public function getLaravelTypeDefs(): string
    {
        return <<<'TYPESCRIPT'
/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    readonly VITE_APP_URL: string;
    readonly VITE_PUSHER_APP_KEY: string;
    readonly VITE_PUSHER_HOST: string;
    readonly VITE_PUSHER_PORT: string;
    readonly VITE_PUSHER_SCHEME: string;
    readonly VITE_PUSHER_APP_CLUSTER: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}

// Laravel Echo types
declare global {
    interface Window {
        axios: import('axios').AxiosInstance;
        Echo?: import('laravel-echo').default;
    }
}

export {};
TYPESCRIPT;
    }

    /**
     * Get Inertia page props type definition.
     */
    public function getInertiaTypeDefs(): string
    {
        $componentTypes = match ($this->framework) {
            self::FRAMEWORK_INERTIA_VUE => 'import type { DefineComponent } from \'vue\';',
            self::FRAMEWORK_INERTIA_REACT => 'import type { ComponentType } from \'react\';',
            default => '',
        };

        return <<<TYPESCRIPT
{$componentTypes}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    errors: Record<string, string>;
    ziggy: {
        location: string;
        url: string;
        port: number | null;
        defaults: Record<string, unknown>;
        routes: Record<string, { uri: string; methods: string[] }>;
    };
}

declare module '@inertiajs/core' {
    interface PageProps extends PageProps {}
}

TYPESCRIPT;
    }

    /**
     * Get recommended scripts for package.json.
     *
     * @return array<string, string>
     */
    public function getScripts(): array
    {
        $typeCheck = match ($this->framework) {
            self::FRAMEWORK_VUE, self::FRAMEWORK_INERTIA_VUE => 'vue-tsc --noEmit',
            self::FRAMEWORK_REACT, self::FRAMEWORK_INERTIA_REACT => 'tsc --noEmit',
            default => 'tsc --noEmit',
        };

        return [
            'dev' => 'vite',
            'build' => 'vite build',
            'preview' => 'vite preview',
            'type-check' => $typeCheck,
            'lint' => 'eslint resources/js --ext .ts,.tsx,.vue --fix',
            'format' => 'prettier --write resources/js/**/*.{ts,tsx,vue}',
            'ci' => 'npm run type-check && npm run lint && npm run build',
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function addVueConfig(array $base): array
    {
        $base['compilerOptions']['jsx'] = 'preserve';
        $base['compilerOptions']['jsxImportSource'] = 'vue';
        $base['include'][] = 'resources/js/**/*.vue';
        $base['vueCompilerOptions'] = [
            'target' => 3.4,
        ];

        return $base;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function addReactConfig(array $base): array
    {
        $base['compilerOptions']['jsx'] = 'react-jsx';
        $base['compilerOptions']['jsxImportSource'] = 'react';
        $base['include'][] = 'resources/js/**/*.tsx';

        return $base;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function addVueEslintConfig(array $base): array
    {
        $base['extends'] = [
            'eslint:recommended',
            'plugin:vue/vue3-recommended',
            '@vue/eslint-config-typescript/recommended',
            '@vue/eslint-config-prettier/skip-formatting',
        ];
        $base['parser'] = 'vue-eslint-parser';
        $base['parserOptions']['parser'] = '@typescript-eslint/parser';
        $base['rules']['vue/multi-word-component-names'] = 'off';

        return $base;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function addReactEslintConfig(array $base): array
    {
        $base['extends'] = [
            'eslint:recommended',
            'plugin:react/recommended',
            'plugin:react-hooks/recommended',
            'plugin:@typescript-eslint/recommended',
        ];
        $base['plugins'] = ['react', 'react-hooks', '@typescript-eslint'];
        $base['parserOptions']['ecmaFeatures'] = ['jsx' => true];
        $base['settings'] = [
            'react' => ['version' => 'detect'],
        ];
        $base['rules']['react/react-in-jsx-scope'] = 'off';
        $base['rules']['@typescript-eslint/no-unused-vars'] = ['warn', ['argsIgnorePattern' => '^_']];

        return $base;
    }
}
