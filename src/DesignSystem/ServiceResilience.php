<?php

declare(strict_types=1);

namespace LaraForge\DesignSystem;

/**
 * Service Resilience
 *
 * Patterns for handling service failures, circuit breakers, and graceful degradation.
 * Ensures robust handling of external service dependencies.
 */
final class ServiceResilience
{
    /**
     * Circuit breaker states.
     */
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Default circuit breaker configuration.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_CIRCUIT_CONFIG = [
        'failure_threshold' => 5,
        'success_threshold' => 3,
        'timeout' => 60, // seconds
        'half_open_max_calls' => 3,
    ];

    /**
     * Default retry configuration.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_RETRY_CONFIG = [
        'max_attempts' => 3,
        'initial_delay' => 100, // milliseconds
        'max_delay' => 5000, // milliseconds
        'multiplier' => 2.0,
        'jitter' => 0.1,
    ];

    /**
     * Service health check configuration.
     *
     * @var array<string, mixed>
     */
    private const HEALTH_CHECK_CONFIG = [
        'interval' => 30, // seconds
        'timeout' => 5, // seconds
        'healthy_threshold' => 2,
        'unhealthy_threshold' => 3,
    ];

    /**
     * Get circuit breaker pattern for a service.
     *
     * @return array<string, mixed>
     */
    public function getCircuitBreakerPattern(string $serviceName): array
    {
        return [
            'service' => $serviceName,
            'config' => self::DEFAULT_CIRCUIT_CONFIG,
            'implementation' => $this->generateCircuitBreakerClass($serviceName),
            'usage' => $this->getCircuitBreakerUsage($serviceName),
        ];
    }

    /**
     * Get retry pattern with exponential backoff.
     *
     * @return array<string, mixed>
     */
    public function getRetryPattern(): array
    {
        return [
            'config' => self::DEFAULT_RETRY_CONFIG,
            'implementation' => $this->generateRetryClass(),
            'usage' => $this->getRetryUsage(),
        ];
    }

    /**
     * Get graceful degradation strategies.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDegradationStrategies(): array
    {
        return [
            'cache_fallback' => [
                'description' => 'Return cached data when service is unavailable',
                'use_case' => 'Read operations with acceptable stale data',
                'implementation' => $this->getCacheFallbackImplementation(),
            ],
            'default_response' => [
                'description' => 'Return sensible default when service fails',
                'use_case' => 'Non-critical data that has reasonable defaults',
                'implementation' => $this->getDefaultResponseImplementation(),
            ],
            'queue_for_retry' => [
                'description' => 'Queue operation for later retry',
                'use_case' => 'Write operations that must eventually succeed',
                'implementation' => $this->getQueueRetryImplementation(),
            ],
            'partial_response' => [
                'description' => 'Return partial data excluding failed service',
                'use_case' => 'Aggregate responses where some sources may fail',
                'implementation' => $this->getPartialResponseImplementation(),
            ],
            'feature_toggle' => [
                'description' => 'Disable feature when dependency is unhealthy',
                'use_case' => 'Optional features that depend on external services',
                'implementation' => $this->getFeatureToggleImplementation(),
            ],
        ];
    }

    /**
     * Get frontend resilience patterns.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFrontendPatterns(): array
    {
        return [
            'button_disable' => [
                'description' => 'Disable buttons during pending operations',
                'blade' => $this->getBladeButtonPattern(),
                'livewire' => $this->getLivewireButtonPattern(),
                'vue' => $this->getVueButtonPattern(),
                'react' => $this->getReactButtonPattern(),
            ],
            'loading_states' => [
                'description' => 'Show loading indicators during async operations',
                'patterns' => [
                    'skeleton' => 'Replace content with skeleton placeholder',
                    'spinner' => 'Overlay spinner on existing content',
                    'progress' => 'Show progress bar for long operations',
                ],
            ],
            'error_boundaries' => [
                'description' => 'Catch and display errors gracefully',
                'vue' => $this->getVueErrorBoundary(),
                'react' => $this->getReactErrorBoundary(),
            ],
            'offline_support' => [
                'description' => 'Handle offline scenarios',
                'patterns' => [
                    'service_worker' => 'Cache API responses',
                    'local_storage' => 'Persist data locally',
                    'optimistic_updates' => 'Update UI before server confirms',
                ],
            ],
            'retry_ui' => [
                'description' => 'Allow users to retry failed operations',
                'implementation' => $this->getRetryUiPattern(),
            ],
        ];
    }

    /**
     * Get health check configuration.
     *
     * @return array<string, mixed>
     */
    public function getHealthCheckPattern(): array
    {
        return [
            'config' => self::HEALTH_CHECK_CONFIG,
            'implementation' => $this->generateHealthCheckClass(),
            'endpoints' => [
                '/health' => 'Basic health check',
                '/health/live' => 'Liveness probe (is process running)',
                '/health/ready' => 'Readiness probe (can accept traffic)',
                '/health/dependencies' => 'Check all dependencies',
            ],
        ];
    }

    /**
     * Get service toggle configuration.
     *
     * @return array<string, mixed>
     */
    public function getServiceTogglePattern(): array
    {
        return [
            'description' => 'Enable/disable services at runtime',
            'config_example' => [
                'services' => [
                    'payment_gateway' => [
                        'enabled' => true,
                        'fallback' => 'queue_for_retry',
                    ],
                    'email_service' => [
                        'enabled' => true,
                        'fallback' => 'queue_for_retry',
                    ],
                    'sms_service' => [
                        'enabled' => true,
                        'fallback' => 'log_only',
                    ],
                ],
            ],
            'implementation' => $this->generateServiceToggleClass(),
        ];
    }

    /**
     * Get monitoring and alerting recommendations.
     *
     * @return array<string, mixed>
     */
    public function getMonitoringRecommendations(): array
    {
        return [
            'metrics' => [
                'request_duration' => 'Time taken for service calls',
                'error_rate' => 'Percentage of failed requests',
                'circuit_state' => 'Current circuit breaker state',
                'retry_count' => 'Number of retry attempts',
                'queue_depth' => 'Pending retry queue size',
            ],
            'alerts' => [
                'high_error_rate' => [
                    'threshold' => 0.05, // 5%
                    'window' => '5m',
                    'severity' => 'warning',
                ],
                'circuit_open' => [
                    'condition' => 'state == open',
                    'severity' => 'critical',
                ],
                'degraded_service' => [
                    'condition' => 'using_fallback == true',
                    'severity' => 'warning',
                ],
            ],
            'dashboards' => [
                'Service Health Overview',
                'Circuit Breaker Status',
                'Retry Queue Metrics',
                'Dependency Status',
            ],
        ];
    }

    private function generateCircuitBreakerClass(string $serviceName): string
    {
        $className = ucfirst($serviceName).'CircuitBreaker';

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Services\CircuitBreakers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class {$className}
{
    private const CACHE_KEY = 'circuit_breaker:{$serviceName}';

    public function __construct(
        private readonly int \$failureThreshold = 5,
        private readonly int \$successThreshold = 3,
        private readonly int \$timeout = 60,
    ) {}

    public function isAvailable(): bool
    {
        \$state = \$this->getState();

        return match (\$state['status']) {
            'closed' => true,
            'open' => \$this->shouldAttemptReset(\$state),
            'half_open' => \$state['half_open_calls'] < 3,
            default => true,
        };
    }

    public function recordSuccess(): void
    {
        \$state = \$this->getState();

        if (\$state['status'] === 'half_open') {
            \$state['success_count']++;
            if (\$state['success_count'] >= \$this->successThreshold) {
                \$this->close();
                return;
            }
        }

        \$state['failure_count'] = 0;
        \$this->saveState(\$state);
    }

    public function recordFailure(): void
    {
        \$state = \$this->getState();
        \$state['failure_count']++;
        \$state['last_failure'] = time();

        if (\$state['failure_count'] >= \$this->failureThreshold) {
            \$this->open();
            Log::warning('{$serviceName} circuit breaker opened', [
                'failures' => \$state['failure_count'],
            ]);
        }

        \$this->saveState(\$state);
    }

    public function execute(callable \$operation, callable \$fallback = null): mixed
    {
        if (!\$this->isAvailable()) {
            if (\$fallback !== null) {
                return \$fallback();
            }
            throw new ServiceUnavailableException('{$serviceName} is currently unavailable');
        }

        try {
            \$result = \$operation();
            \$this->recordSuccess();
            return \$result;
        } catch (\Throwable \$e) {
            \$this->recordFailure();

            if (\$fallback !== null) {
                return \$fallback();
            }

            throw \$e;
        }
    }

    private function getState(): array
    {
        return Cache::get(self::CACHE_KEY, [
            'status' => 'closed',
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure' => null,
            'opened_at' => null,
            'half_open_calls' => 0,
        ]);
    }

    private function saveState(array \$state): void
    {
        Cache::put(self::CACHE_KEY, \$state, 3600);
    }

    private function open(): void
    {
        \$this->saveState([
            'status' => 'open',
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure' => time(),
            'opened_at' => time(),
            'half_open_calls' => 0,
        ]);
    }

    private function close(): void
    {
        \$this->saveState([
            'status' => 'closed',
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure' => null,
            'opened_at' => null,
            'half_open_calls' => 0,
        ]);
    }

    private function shouldAttemptReset(array \$state): bool
    {
        if (\$state['opened_at'] === null) {
            return true;
        }

        return (time() - \$state['opened_at']) >= \$this->timeout;
    }
}
PHP;
    }

    private function getCircuitBreakerUsage(string $serviceName): string
    {
        return <<<PHP
// Usage example
\$circuitBreaker = new {$serviceName}CircuitBreaker();

\$result = \$circuitBreaker->execute(
    operation: fn() => \$this->externalService->call(),
    fallback: fn() => \$this->getCachedResponse(),
);
PHP;
    }

    private function generateRetryClass(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

final class RetryHandler
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $initialDelay = 100,
        private readonly int $maxDelay = 5000,
        private readonly float $multiplier = 2.0,
        private readonly float $jitter = 0.1,
    ) {}

    public function execute(callable $operation, array $retryableExceptions = []): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                if (!$this->isRetryable($e, $retryableExceptions)) {
                    throw $e;
                }

                $attempt++;

                if ($attempt < $this->maxAttempts) {
                    $this->sleep($attempt);
                }
            }
        }

        throw $lastException;
    }

    private function isRetryable(\Throwable $e, array $retryableExceptions): bool
    {
        if ($retryableExceptions === []) {
            return true;
        }

        foreach ($retryableExceptions as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function sleep(int $attempt): void
    {
        $delay = min(
            $this->initialDelay * ($this->multiplier ** ($attempt - 1)),
            $this->maxDelay
        );

        // Add jitter
        $jitterAmount = $delay * $this->jitter;
        $delay += random_int((int) -$jitterAmount, (int) $jitterAmount);

        usleep((int) ($delay * 1000));
    }
}
PHP;
    }

    private function getRetryUsage(): string
    {
        return <<<'PHP'
// Usage example
$retry = new RetryHandler(maxAttempts: 3);

$result = $retry->execute(
    operation: fn() => $this->api->makeRequest(),
    retryableExceptions: [ConnectionException::class, TimeoutException::class],
);
PHP;
    }

    private function getCacheFallbackImplementation(): string
    {
        return <<<'PHP'
public function getData(): array
{
    try {
        $data = $this->externalService->fetch();
        Cache::put('service_data', $data, 3600);
        return $data;
    } catch (ServiceException $e) {
        $cached = Cache::get('service_data');
        if ($cached !== null) {
            Log::warning('Using cached data due to service failure', [
                'error' => $e->getMessage(),
            ]);
            return $cached;
        }
        throw $e;
    }
}
PHP;
    }

    private function getDefaultResponseImplementation(): string
    {
        return <<<'PHP'
public function getFeatureFlags(): array
{
    try {
        return $this->featureFlagService->getFlags();
    } catch (ServiceException $e) {
        Log::warning('Using default feature flags', ['error' => $e->getMessage()]);
        return $this->getDefaultFlags();
    }
}

private function getDefaultFlags(): array
{
    return [
        'new_checkout' => false,
        'dark_mode' => true,
        'beta_features' => false,
    ];
}
PHP;
    }

    private function getQueueRetryImplementation(): string
    {
        return <<<'PHP'
public function processPayment(Payment $payment): void
{
    try {
        $this->paymentGateway->process($payment);
    } catch (ServiceException $e) {
        Log::warning('Payment queued for retry', [
            'payment_id' => $payment->id,
            'error' => $e->getMessage(),
        ]);

        ProcessPaymentJob::dispatch($payment)
            ->delay(now()->addMinutes(5))
            ->onQueue('payments');
    }
}
PHP;
    }

    private function getPartialResponseImplementation(): string
    {
        return <<<'PHP'
public function getDashboardData(): array
{
    $data = [
        'user' => $this->getUser(),
        'notifications' => [],
        'analytics' => [],
    ];

    try {
        $data['notifications'] = $this->notificationService->getUnread();
    } catch (ServiceException $e) {
        Log::warning('Could not fetch notifications');
        $data['notifications_error'] = true;
    }

    try {
        $data['analytics'] = $this->analyticsService->getSummary();
    } catch (ServiceException $e) {
        Log::warning('Could not fetch analytics');
        $data['analytics_error'] = true;
    }

    return $data;
}
PHP;
    }

    private function getFeatureToggleImplementation(): string
    {
        return <<<'PHP'
public function isFeatureAvailable(string $feature): bool
{
    $service = config("features.{$feature}.service");

    if ($service && !$this->serviceHealth->isHealthy($service)) {
        return false;
    }

    return config("features.{$feature}.enabled", false);
}
PHP;
    }

    private function getBladeButtonPattern(): string
    {
        return <<<'BLADE'
<button
    type="submit"
    wire:loading.attr="disabled"
    wire:loading.class="opacity-50 cursor-not-allowed"
    class="btn btn-primary"
>
    <span wire:loading.remove>Submit</span>
    <span wire:loading>Processing...</span>
</button>
BLADE;
    }

    private function getLivewireButtonPattern(): string
    {
        return <<<'PHP'
// In Livewire component
public bool $isProcessing = false;

public function submit(): void
{
    $this->isProcessing = true;

    try {
        // Process...
    } finally {
        $this->isProcessing = false;
    }
}

// In Blade view
<button
    wire:click="submit"
    :disabled="$isProcessing"
    class="{{ $isProcessing ? 'opacity-50' : '' }}"
>
    {{ $isProcessing ? 'Processing...' : 'Submit' }}
</button>
PHP;
    }

    private function getVueButtonPattern(): string
    {
        return <<<'VUE'
<template>
  <button
    @click="submit"
    :disabled="isProcessing"
    :class="{ 'opacity-50 cursor-not-allowed': isProcessing }"
  >
    <span v-if="!isProcessing">Submit</span>
    <span v-else>
      <LoadingSpinner class="w-4 h-4 mr-2" />
      Processing...
    </span>
  </button>
</template>

<script setup>
import { ref } from 'vue';

const isProcessing = ref(false);

async function submit() {
  isProcessing.value = true;
  try {
    await api.submit();
  } catch (error) {
    // Handle error
  } finally {
    isProcessing.value = false;
  }
}
</script>
VUE;
    }

    private function getReactButtonPattern(): string
    {
        return <<<'TSX'
function SubmitButton() {
  const [isProcessing, setIsProcessing] = useState(false);

  const handleSubmit = async () => {
    setIsProcessing(true);
    try {
      await api.submit();
    } catch (error) {
      // Handle error
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <button
      onClick={handleSubmit}
      disabled={isProcessing}
      className={isProcessing ? 'opacity-50 cursor-not-allowed' : ''}
    >
      {isProcessing ? (
        <>
          <LoadingSpinner className="w-4 h-4 mr-2" />
          Processing...
        </>
      ) : (
        'Submit'
      )}
    </button>
  );
}
TSX;
    }

    private function getVueErrorBoundary(): string
    {
        return <<<'VUE'
<template>
  <div v-if="error" class="error-boundary">
    <h3>Something went wrong</h3>
    <p>{{ error.message }}</p>
    <button @click="retry">Try Again</button>
  </div>
  <slot v-else />
</template>

<script setup>
import { ref, onErrorCaptured } from 'vue';

const error = ref(null);

onErrorCaptured((err) => {
  error.value = err;
  return false;
});

function retry() {
  error.value = null;
}
</script>
VUE;
    }

    private function getReactErrorBoundary(): string
    {
        return <<<'TSX'
class ErrorBoundary extends React.Component {
  state = { hasError: false, error: null };

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('Error caught by boundary:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="error-boundary">
          <h3>Something went wrong</h3>
          <p>{this.state.error?.message}</p>
          <button onClick={() => this.setState({ hasError: false })}>
            Try Again
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}
TSX;
    }

    private function getRetryUiPattern(): string
    {
        return <<<'HTML'
<div x-data="{ failed: false, retrying: false }">
  <div x-show="failed" class="bg-red-50 p-4 rounded">
    <p class="text-red-700">Failed to load data</p>
    <button
      @click="retry()"
      :disabled="retrying"
      class="mt-2 btn btn-sm"
    >
      <span x-show="!retrying">Retry</span>
      <span x-show="retrying">Retrying...</span>
    </button>
  </div>
</div>
HTML;
    }

    private function generateHealthCheckClass(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class HealthCheckService
{
    public function check(): array
    {
        return [
            'status' => $this->isHealthy() ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'redis' => $this->checkRedis(),
                'storage' => $this->checkStorage(),
            ],
        ];
    }

    public function isHealthy(): bool
    {
        return $this->checkDatabase()['healthy']
            && $this->checkCache()['healthy'];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['healthy' => true, 'latency_ms' => $this->measureLatency(fn() => DB::select('SELECT 1'))];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            Cache::put('health_check', true, 10);
            $result = Cache::get('health_check');
            return ['healthy' => $result === true];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return ['healthy' => true];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = \Storage::disk();
            $disk->put('health_check.txt', 'ok');
            $disk->delete('health_check.txt');
            return ['healthy' => true];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function measureLatency(callable $operation): float
    {
        $start = microtime(true);
        $operation();
        return round((microtime(true) - $start) * 1000, 2);
    }
}
PHP;
    }

    private function generateServiceToggleClass(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;

final class ServiceToggle
{
    public function isEnabled(string $service): bool
    {
        return Config::get("services.toggles.{$service}.enabled", true);
    }

    public function disable(string $service): void
    {
        Config::set("services.toggles.{$service}.enabled", false);
    }

    public function enable(string $service): void
    {
        Config::set("services.toggles.{$service}.enabled", true);
    }

    public function getFallbackStrategy(string $service): string
    {
        return Config::get("services.toggles.{$service}.fallback", 'throw');
    }

    public function executeWithToggle(string $service, callable $operation, callable $fallback = null): mixed
    {
        if (!$this->isEnabled($service)) {
            $strategy = $this->getFallbackStrategy($service);

            return match ($strategy) {
                'fallback' => $fallback ? $fallback() : null,
                'null' => null,
                'throw' => throw new ServiceDisabledException("{$service} is currently disabled"),
                default => null,
            };
        }

        return $operation();
    }
}
PHP;
    }

    /**
     * Create instance.
     */
    public static function create(): self
    {
        return new self;
    }
}
