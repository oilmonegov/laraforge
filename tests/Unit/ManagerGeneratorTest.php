<?php

declare(strict_types=1);

use LaraForge\Generators\ManagerGenerator;
use LaraForge\LaraForge;

describe('ManagerGenerator', function () {
    it('has correct identifier', function () {
        $generator = new ManagerGenerator(laraforge());

        expect($generator->identifier())->toBe('manager');
    });

    it('has name and description', function () {
        $generator = new ManagerGenerator(laraforge());

        expect($generator->name())->toBe('Manager Pattern');
        expect($generator->description())->toContain('Manager pattern');
    });

    it('supports TDD mode', function () {
        $generator = new ManagerGenerator(laraforge());

        expect($generator->supportsTdd())->toBeTrue();
    });

    it('defines default methods', function () {
        expect(ManagerGenerator::DEFAULT_METHODS)->toHaveKeys([
            'all',
            'find',
            'create',
            'update',
            'delete',
        ]);
    });

    it('defines options with defaults', function () {
        $generator = new ManagerGenerator(laraforge());
        $options = $generator->options();

        expect($options)->toHaveKey('service');
        expect($options)->toHaveKey('drivers');
        expect($options)->toHaveKey('methods');
        expect($options)->toHaveKey('use_saloon');
        expect($options)->toHaveKey('include_config');
        expect($options)->toHaveKey('include_provider');
        expect($options)->toHaveKey('style');

        expect($options['service']['required'])->toBeTrue();
        expect($options['drivers']['default'])->toBe([]);
        expect($options['use_saloon']['default'])->toBeTrue();
        expect($options['include_config']['default'])->toBeTrue();
        expect($options['include_provider']['default'])->toBeTrue();
    });

    it('generates manager class with interface', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
        ]);

        $fileNames = array_map(fn ($f) => basename($f), $files);

        expect($fileNames)->toContain('PaymentInterface.php');
        expect($fileNames)->toContain('PaymentManager.php');
        expect($fileNames)->toContain('DefaultDriver.php');
        expect($fileNames)->toContain('DefaultConnector.php');
        expect($fileNames)->toContain('PaymentServiceProvider.php');
        expect($fileNames)->toContain('payment.php');
    });

    it('generates manager with multiple drivers', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'drivers' => ['Stripe', 'PayPal', 'Square'],
        ]);

        $fileNames = array_map(fn ($f) => basename($f), $files);

        expect($fileNames)->toContain('StripeDriver.php');
        expect($fileNames)->toContain('PayPalDriver.php');
        expect($fileNames)->toContain('SquareDriver.php');
        expect($fileNames)->toContain('StripeConnector.php');
        expect($fileNames)->toContain('PayPalConnector.php');
        expect($fileNames)->toContain('SquareConnector.php');
    });

    it('generates interface with custom methods', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'methods' => ['charge', 'refund', 'getBalance'],
        ]);

        // Find the interface file
        $interfaceFile = null;
        foreach ($files as $file) {
            if (str_ends_with($file, 'PaymentInterface.php')) {
                $interfaceFile = $file;
                break;
            }
        }

        expect($interfaceFile)->not->toBeNull();

        $content = file_get_contents($interfaceFile);
        expect($content)->toContain('public function charge(');
        expect($content)->toContain('public function refund(');
        expect($content)->toContain('public function getBalance(');
    });

    it('generates manager class that extends Illuminate Manager', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Notification',
        ]);

        // Find the manager file
        $managerFile = null;
        foreach ($files as $file) {
            if (str_ends_with($file, 'NotificationManager.php')) {
                $managerFile = $file;
                break;
            }
        }

        $content = file_get_contents($managerFile);
        expect($content)->toContain('use Illuminate\Support\Manager');
        expect($content)->toContain('class NotificationManager extends Manager');
        expect($content)->toContain('public function getDefaultDriver(): string');
    });

    it('generates driver with Saloon integration by default', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'drivers' => ['Stripe'],
        ]);

        // Find the driver file
        $driverFile = null;
        foreach ($files as $file) {
            if (str_ends_with($file, 'StripeDriver.php')) {
                $driverFile = $file;
                break;
            }
        }

        $content = file_get_contents($driverFile);
        expect($content)->toContain('StripeConnector');
        expect($content)->toContain('public function connector()');
    });

    it('generates driver without Saloon when disabled', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Cache',
            'drivers' => ['Redis'],
            'use_saloon' => false,
        ]);

        $fileNames = array_map(fn ($f) => basename($f), $files);

        // Should not have connector
        expect($fileNames)->not->toContain('RedisConnector.php');

        // Find the driver file
        $driverFile = null;
        foreach ($files as $file) {
            if (str_ends_with($file, 'RedisDriver.php')) {
                $driverFile = $file;
                break;
            }
        }

        $content = file_get_contents($driverFile);
        expect($content)->not->toContain('Connector');
    });

    it('generates Saloon connector with proper structure', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'drivers' => ['Stripe'],
        ]);

        // Find the connector file
        $connectorFile = null;
        foreach ($files as $file) {
            if (str_ends_with($file, 'StripeConnector.php')) {
                $connectorFile = $file;
                break;
            }
        }

        $content = file_get_contents($connectorFile);
        expect($content)->toContain('use Saloon\Http\Connector');
        expect($content)->toContain('class StripeConnector extends Connector');
        expect($content)->toContain('public function resolveBaseUrl(): string');
        expect($content)->toContain('protected function defaultHeaders(): array');
    });

    it('generates service provider', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
        ]);

        // Find the provider file
        $providerFile = null;
        foreach ($files as $file) {
            if (str_ends_with($file, 'PaymentServiceProvider.php')) {
                $providerFile = $file;
                break;
            }
        }

        $content = file_get_contents($providerFile);
        expect($content)->toContain('namespace App\Providers');
        expect($content)->toContain('class PaymentServiceProvider extends ServiceProvider');
        expect($content)->toContain('$this->app->singleton(PaymentManager::class');
        expect($content)->toContain('$this->app->alias(PaymentManager::class, PaymentInterface::class)');
    });

    it('generates config file', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'drivers' => ['Stripe', 'PayPal'],
        ]);

        // Find the config file
        $configFile = null;
        foreach ($files as $file) {
            if (str_ends_with($file, 'payment.php')) {
                $configFile = $file;
                break;
            }
        }

        $content = file_get_contents($configFile);
        expect($content)->toContain("'driver' => env('PAYMENT_DRIVER'");
        expect($content)->toContain("'stripe' => [");
        expect($content)->toContain("'pay-pal' => [");
        expect($content)->toContain('PAYMENT_STRIPE_API_KEY');
        expect($content)->toContain('PAYMENT_PAY_PAL_API_KEY');
    });

    it('can skip config and provider generation', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'include_config' => false,
            'include_provider' => false,
        ]);

        $fileNames = array_map(fn ($f) => basename($f), $files);

        expect($fileNames)->not->toContain('payment.php');
        expect($fileNames)->not->toContain('PaymentServiceProvider.php');
    });

    it('generates tests in TDD mode', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'drivers' => ['Stripe'],
            'style' => 'tdd',
        ]);

        $fileNames = array_map(fn ($f) => basename($f), $files);

        // Tests should come first
        expect($fileNames)->toContain('PaymentManagerTest.php');
        expect($fileNames)->toContain('StripeDriverTest.php');

        // Implementation follows
        expect($fileNames)->toContain('PaymentManager.php');
        expect($fileNames)->toContain('StripeDriver.php');
    });

    it('generates driver test with Saloon assertions', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'drivers' => ['Stripe'],
            'style' => 'tdd',
        ]);

        // Find the driver test file
        $testFile = null;
        foreach ($files as $file) {
            if (str_ends_with($file, 'StripeDriverTest.php')) {
                $testFile = $file;
                break;
            }
        }

        $content = file_get_contents($testFile);
        expect($content)->toContain('StripeConnector');
        expect($content)->toContain('provides access to the Saloon connector');
    });

    it('converts service name to StudlyCase', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'payment-gateway',
        ]);

        $fileNames = array_map(fn ($f) => basename($f), $files);

        expect($fileNames)->toContain('PaymentGatewayManager.php');
        expect($fileNames)->toContain('PaymentGatewayInterface.php');
    });

    it('generates correct directory structure', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ManagerGenerator($laraforge);

        $files = $generator->generate([
            'service' => 'Payment',
            'drivers' => ['Stripe'],
        ]);

        // Check directory structure
        $paths = array_map(fn ($f) => str_replace($tempDir.'/', '', $f), $files);

        expect($paths)->toContain('app/Services/Payment/Contracts/PaymentInterface.php');
        expect($paths)->toContain('app/Services/Payment/PaymentManager.php');
        expect($paths)->toContain('app/Services/Payment/Drivers/StripeDriver.php');
        expect($paths)->toContain('app/Services/Payment/Connectors/StripeConnector.php');
        expect($paths)->toContain('app/Providers/PaymentServiceProvider.php');
        expect($paths)->toContain('config/payment.php');
    });
});
