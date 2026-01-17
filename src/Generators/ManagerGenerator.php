<?php

declare(strict_types=1);

namespace LaraForge\Generators;

use LaraForge\Support\Generator;
use LaraForge\Support\TddAwareGenerator;

final class ManagerGenerator extends Generator
{
    use TddAwareGenerator;

    public const DEFAULT_METHODS = [
        'all' => 'Retrieve all resources',
        'find' => 'Find a resource by ID',
        'create' => 'Create a new resource',
        'update' => 'Update an existing resource',
        'delete' => 'Delete a resource',
    ];

    public function identifier(): string
    {
        return 'manager';
    }

    public function name(): string
    {
        return 'Manager Pattern';
    }

    public function description(): string
    {
        return 'Generates Laravel Manager pattern with pluggable drivers and optional Saloon integration';
    }

    public function supportsTdd(): bool
    {
        return true;
    }

    public function options(): array
    {
        return [
            'service' => [
                'type' => 'string',
                'description' => 'The service name (e.g., "Payment", "Notification")',
                'required' => true,
            ],
            'drivers' => [
                'type' => 'array',
                'description' => 'List of driver names (e.g., ["Stripe", "PayPal"])',
                'required' => false,
                'default' => [],
            ],
            'methods' => [
                'type' => 'array',
                'description' => 'List of methods for the interface (e.g., ["charge", "refund"])',
                'required' => false,
                'default' => [],
            ],
            'use_saloon' => [
                'type' => 'boolean',
                'description' => 'Generate Saloon HTTP client integration',
                'required' => false,
                'default' => true,
            ],
            'include_config' => [
                'type' => 'boolean',
                'description' => 'Generate config file',
                'required' => false,
                'default' => true,
            ],
            'include_provider' => [
                'type' => 'boolean',
                'description' => 'Generate service provider',
                'required' => false,
                'default' => true,
            ],
            'style' => [
                'type' => 'string',
                'description' => 'Implementation style: "regular" or "tdd"',
                'required' => false,
                'default' => 'regular',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    public function generate(array $options = []): array
    {
        $service = $this->studly($options['service']);
        $drivers = $options['drivers'] ?? [];
        $methods = $options['methods'] ?? [];
        $useSaloon = $options['use_saloon'] ?? true;
        $includeConfig = $options['include_config'] ?? true;
        $includeProvider = $options['include_provider'] ?? true;

        // Use default drivers if none provided
        if (empty($drivers)) {
            $drivers = ['Default'];
        }

        // Use default methods if none provided
        if (empty($methods)) {
            $methods = array_keys(self::DEFAULT_METHODS);
        }

        if ($this->isTddMode($options)) {
            return $this->generateWithTdd($options);
        }

        return $this->generateManager($service, $drivers, $methods, $useSaloon, $includeConfig, $includeProvider);
    }

    /**
     * Generate all manager files.
     *
     * @param  array<string>  $drivers
     * @param  array<string>  $methods
     * @return array<string>
     */
    private function generateManager(
        string $service,
        array $drivers,
        array $methods,
        bool $useSaloon,
        bool $includeConfig,
        bool $includeProvider,
    ): array {
        $generatedFiles = [];

        $className = "{$service}Manager";
        $interfaceName = "{$service}Interface";
        $configKey = $this->snake($service);
        $defaultDriver = $this->kebab($drivers[0] ?? 'default');

        // Prepare driver data
        $driverData = $this->buildDriverData($drivers, $configKey, $service);

        // Prepare method data
        $methodData = $this->buildMethodData($methods);

        // Generate interface
        $generatedFiles[] = $this->generateInterface($service, $interfaceName, $methodData);

        // Generate manager class
        $generatedFiles[] = $this->generateManagerClass(
            $service,
            $className,
            $interfaceName,
            $configKey,
            $defaultDriver,
            $driverData,
            $methodData,
        );

        // Generate drivers
        foreach ($drivers as $driver) {
            $driverName = $this->studly($driver);
            $generatedFiles[] = $this->generateDriver($service, $driverName, $interfaceName, $methodData, $useSaloon);

            // Generate Saloon connector if enabled
            if ($useSaloon) {
                $generatedFiles[] = $this->generateConnector($service, $driverName);
            }
        }

        // Generate service provider
        if ($includeProvider) {
            $generatedFiles[] = $this->generateServiceProvider($service, $className, $interfaceName);
        }

        // Generate config file
        if ($includeConfig) {
            $generatedFiles[] = $this->generateConfig($service, $configKey, $defaultDriver, $driverData);
        }

        return $generatedFiles;
    }

    /**
     * Generate interface file.
     *
     * @param  array<array<string, string>>  $methodData
     */
    private function generateInterface(string $service, string $interfaceName, array $methodData): string
    {
        $content = $this->renderStub('manager-interface', [
            'serviceName' => $service,
            'interfaceName' => $interfaceName,
            'methods' => $methodData,
        ]);

        return $this->writeFile("app/Services/{$service}/Contracts/{$interfaceName}.php", $content);
    }

    /**
     * Generate manager class file.
     *
     * @param  array<array<string, string>>  $driverData
     * @param  array<array<string, string>>  $methodData
     */
    private function generateManagerClass(
        string $service,
        string $className,
        string $interfaceName,
        string $configKey,
        string $defaultDriver,
        array $driverData,
        array $methodData,
    ): string {
        $content = $this->renderStub('manager', [
            'serviceName' => $service,
            'className' => $className,
            'interfaceName' => $interfaceName,
            'configKey' => $configKey,
            'defaultDriver' => $defaultDriver,
            'drivers' => $driverData,
            'methods' => $methodData,
        ]);

        return $this->writeFile("app/Services/{$service}/{$className}.php", $content);
    }

    /**
     * Generate driver file.
     *
     * @param  array<array<string, string>>  $methodData
     */
    private function generateDriver(
        string $service,
        string $driverName,
        string $interfaceName,
        array $methodData,
        bool $useSaloon,
    ): string {
        $stubName = $useSaloon ? 'manager-saloon-driver' : 'manager-driver';

        $content = $this->renderStub($stubName, [
            'serviceName' => $service,
            'driverName' => $driverName,
            'interfaceName' => $interfaceName,
            'methods' => $methodData,
        ]);

        return $this->writeFile("app/Services/{$service}/Drivers/{$driverName}Driver.php", $content);
    }

    /**
     * Generate Saloon connector file.
     */
    private function generateConnector(string $service, string $driverName): string
    {
        $content = $this->renderStub('manager-connector', [
            'serviceName' => $service,
            'driverName' => $driverName,
            'baseUrl' => 'https://api.example.com/v1',
        ]);

        return $this->writeFile("app/Services/{$service}/Connectors/{$driverName}Connector.php", $content);
    }

    /**
     * Generate service provider file.
     */
    private function generateServiceProvider(string $service, string $className, string $interfaceName): string
    {
        $content = $this->renderStub('manager-service-provider', [
            'serviceName' => $service,
            'className' => $className,
            'interfaceName' => $interfaceName,
        ]);

        return $this->writeFile("app/Providers/{$service}ServiceProvider.php", $content);
    }

    /**
     * Generate config file.
     *
     * @param  array<array<string, string>>  $driverData
     */
    private function generateConfig(
        string $service,
        string $configKey,
        string $defaultDriver,
        array $driverData,
    ): string {
        $content = $this->renderStub('manager-config', [
            'serviceName' => $service,
            'serviceNameLower' => strtolower($service),
            'configKey' => $configKey,
            'defaultDriver' => $defaultDriver,
            'envPrefix' => strtoupper($this->snake($service)),
            'drivers' => $driverData,
        ]);

        return $this->writeFile("config/{$configKey}.php", $content);
    }

    /**
     * Build driver data for templates.
     *
     * @param  array<string>  $drivers
     * @return array<array<string, string>>
     */
    private function buildDriverData(array $drivers, string $configKey, string $service): array
    {
        $driverData = [];

        foreach ($drivers as $driver) {
            $driverName = $this->studly($driver);
            $driverKey = $this->kebab($driver);

            $driverData[] = [
                'name' => $driverName,
                'key' => $driverKey,
                'configKey' => $configKey,
                'envKey' => strtoupper($this->snake($service).'_'.$this->snake($driver)),
            ];
        }

        return $driverData;
    }

    /**
     * Build method data for templates.
     *
     * @param  array<string>  $methods
     * @return array<array<string, string>>
     */
    private function buildMethodData(array $methods): array
    {
        $methodData = [];

        foreach ($methods as $method) {
            $methodName = $this->camel($method);
            $description = self::DEFAULT_METHODS[$method] ?? ucfirst(str_replace('_', ' ', $method));

            $methodData[] = [
                'name' => $methodName,
                'description' => $description,
            ];
        }

        return $methodData;
    }

    /**
     * Generate test files for TDD mode.
     *
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    protected function generateTests(array $options): array
    {
        $service = $this->studly($options['service']);
        $drivers = $options['drivers'] ?? ['Default'];
        $methods = $options['methods'] ?? array_keys(self::DEFAULT_METHODS);
        $useSaloon = $options['use_saloon'] ?? true;

        $generatedFiles = [];

        $className = "{$service}Manager";
        $interfaceName = "{$service}Interface";
        $configKey = $this->snake($service);
        $defaultDriver = $this->kebab($drivers[0] ?? 'default');

        $driverData = $this->buildDriverData($drivers, $configKey, $service);
        $methodData = $this->buildMethodData($methods);

        // Generate manager test
        $testContent = $this->renderStub('manager-test', [
            'serviceName' => $service,
            'className' => $className,
            'interfaceName' => $interfaceName,
            'configKey' => $configKey,
            'defaultDriver' => $defaultDriver,
            'drivers' => $driverData,
            'methods' => $methodData,
        ]);
        $generatedFiles[] = $this->writeFile("tests/Unit/Services/{$service}/{$className}Test.php", $testContent);

        // Generate driver tests
        foreach ($drivers as $driver) {
            $driverName = $this->studly($driver);
            $driverTestContent = $this->renderStub('manager-driver-test', [
                'serviceName' => $service,
                'driverName' => $driverName,
                'interfaceName' => $interfaceName,
                'useSaloon' => $useSaloon,
                'methods' => $methodData,
            ]);
            $generatedFiles[] = $this->writeFile(
                "tests/Unit/Services/{$service}/Drivers/{$driverName}DriverTest.php",
                $driverTestContent,
            );
        }

        return $generatedFiles;
    }

    /**
     * Generate implementation files for TDD mode.
     *
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    protected function generateImplementation(array $options): array
    {
        $service = $this->studly($options['service']);
        $drivers = $options['drivers'] ?? ['Default'];
        $methods = $options['methods'] ?? array_keys(self::DEFAULT_METHODS);
        $useSaloon = $options['use_saloon'] ?? true;
        $includeConfig = $options['include_config'] ?? true;
        $includeProvider = $options['include_provider'] ?? true;

        return $this->generateManager($service, $drivers, $methods, $useSaloon, $includeConfig, $includeProvider);
    }
}
