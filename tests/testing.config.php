<?php

declare(strict_types=1);

/**
 * LaraForge Testing Configuration
 *
 * This file defines which test types are enabled and their configuration.
 * Users can progressively enable more test types as their project matures.
 *
 * Usage:
 *   - Set 'enabled' to true/false to toggle test suites
 *   - Configure options for each test type
 *   - Run specific suites with: pest --filter=<suite>
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Core Test Suites (Always Enabled)
    |--------------------------------------------------------------------------
    */
    'unit' => [
        'enabled' => true,
        'path' => 'tests/Unit',
        'description' => 'Unit tests for individual components',
    ],

    'feature' => [
        'enabled' => true,
        'path' => 'tests/Feature',
        'description' => 'Feature/integration tests',
    ],

    /*
    |--------------------------------------------------------------------------
    | Architectural Tests
    |--------------------------------------------------------------------------
    |
    | Enforces coding standards, naming conventions, and dependency rules.
    |
    */
    'architecture' => [
        'enabled' => true,
        'path' => 'tests/Arch',
        'description' => 'Architectural rules and constraints',
        'rules' => [
            'strict_types' => true,
            'final_value_objects' => true,
            'interface_naming' => true,
            'dependency_direction' => true,
            'no_debug_functions' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Property-Based Tests
    |--------------------------------------------------------------------------
    |
    | Verifies properties hold true for all possible inputs using randomized data.
    |
    */
    'property' => [
        'enabled' => true,
        'path' => 'tests/Property',
        'description' => 'Property-based tests with randomized data',
        'iterations' => 100, // Number of random inputs per test
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser/E2E Tests (Optional)
    |--------------------------------------------------------------------------
    |
    | End-to-end tests using Pest Browser plugin.
    | Requires: pestphp/pest-plugin-browser
    |
    */
    'browser' => [
        'enabled' => false, // Enable when you have a web interface
        'path' => 'tests/Browser',
        'description' => 'Browser-based E2E tests',
        'options' => [
            'base_url' => env('APP_URL', 'http://localhost'),
            'headless' => true,
            'screenshots_on_failure' => true,
            'screenshots_path' => 'tests/Browser/screenshots',
            'viewport' => [
                'width' => 1920,
                'height' => 1080,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stress/Performance Tests (Optional)
    |--------------------------------------------------------------------------
    |
    | Performance and load testing using Pest Stressless plugin.
    | Requires: pestphp/pest-plugin-stressless
    |
    */
    'stress' => [
        'enabled' => true,
        'path' => 'tests/Stress',
        'description' => 'Stress and performance tests',
        'options' => [
            'concurrency' => [
                'low' => 10,
                'medium' => 50,
                'high' => 100,
            ],
            'duration' => [
                'quick' => 5,   // seconds
                'normal' => 30,
                'extended' => 120,
            ],
            'thresholds' => [
                'max_response_time_ms' => 500,
                'max_memory_growth_mb' => 10,
                'min_requests_per_second' => 100,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mutation Tests (Optional)
    |--------------------------------------------------------------------------
    |
    | Tests that your tests actually catch bugs by introducing mutations.
    | Requires: infection/infection
    |
    */
    'mutation' => [
        'enabled' => true,
        'description' => 'Mutation testing with Infection',
        'options' => [
            'min_msi' => 70,           // Minimum Mutation Score Indicator
            'min_covered_msi' => 80,    // Minimum covered MSI
            'threads' => 4,
            'only_covered' => true,     // Only test covered code
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contract Tests (Optional)
    |--------------------------------------------------------------------------
    |
    | API contract testing to ensure API compatibility.
    |
    */
    'contract' => [
        'enabled' => false, // Enable when you have APIs
        'path' => 'tests/Contract',
        'description' => 'API contract tests',
        'options' => [
            'provider' => null,         // e.g., 'pact', 'openapi'
            'spec_path' => 'docs/api',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot Tests (Optional)
    |--------------------------------------------------------------------------
    |
    | Snapshot testing for generated output validation.
    |
    */
    'snapshot' => [
        'enabled' => false,
        'path' => 'tests/Snapshot',
        'description' => 'Snapshot tests for generated content',
        'options' => [
            'update_snapshots' => false,
            'snapshots_path' => 'tests/__snapshots__',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Static Analysis
    |--------------------------------------------------------------------------
    |
    | PHPStan configuration for static analysis.
    |
    */
    'static_analysis' => [
        'enabled' => true,
        'tool' => 'phpstan',
        'level' => 5,
        'paths' => ['src'],
        'excludes' => ['src/Commands', 'src/ComposerScripts.php'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Style
    |--------------------------------------------------------------------------
    |
    | Laravel Pint configuration for code style.
    |
    */
    'code_style' => [
        'enabled' => true,
        'tool' => 'pint',
        'preset' => 'laravel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Coverage Requirements
    |--------------------------------------------------------------------------
    */
    'coverage' => [
        'enabled' => true,
        'min_percentage' => 80,
        'report_formats' => ['html', 'text'],
        'exclude' => [
            'src/Commands',
            'src/ComposerScripts.php',
        ],
    ],
];
