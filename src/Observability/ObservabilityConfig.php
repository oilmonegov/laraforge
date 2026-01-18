<?php

declare(strict_types=1);

namespace LaraForge\Observability;

/**
 * Observability Configuration
 *
 * Provides configuration helpers and recommendations for monitoring,
 * logging, metrics, tracing, and alerting based on project scale.
 */
final class ObservabilityConfig
{
    /**
     * Supported APM providers.
     */
    public const PROVIDER_NONE = 'none';

    public const PROVIDER_SENTRY = 'sentry';

    public const PROVIDER_DATADOG = 'datadog';

    public const PROVIDER_NEW_RELIC = 'newrelic';

    public const PROVIDER_SCOUT = 'scout';

    public const PROVIDER_BUGSNAG = 'bugsnag';

    /**
     * Supported metrics providers.
     */
    public const METRICS_PROMETHEUS = 'prometheus';

    public const METRICS_STATSD = 'statsd';

    public const METRICS_CLOUDWATCH = 'cloudwatch';

    /**
     * Supported log aggregation.
     */
    public const LOGS_ELK = 'elk';

    public const LOGS_DATADOG = 'datadog';

    public const LOGS_PAPERTRAIL = 'papertrail';

    public const LOGS_CLOUDWATCH = 'cloudwatch';

    public const LOGS_BETTERSTACK = 'betterstack';

    public function __construct(
        private readonly string $projectScale = 'small',
        private readonly string $environment = 'production',
    ) {}

    /**
     * Get recommended observability stack based on project scale.
     *
     * @return array<string, mixed>
     */
    public function getRecommendedStack(): array
    {
        return match ($this->projectScale) {
            'prototype' => $this->getPrototypeStack(),
            'small' => $this->getSmallStack(),
            'medium' => $this->getMediumStack(),
            'large' => $this->getLargeStack(),
            'massive' => $this->getMassiveStack(),
            default => $this->getSmallStack(),
        };
    }

    /**
     * Get Laravel packages for observability.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getLaravelPackages(): array
    {
        return [
            'telescope' => [
                'package' => 'laravel/telescope',
                'description' => 'Debug assistant for Laravel applications',
                'use_cases' => [
                    'Request/response inspection',
                    'Exception tracking',
                    'Database query monitoring',
                    'Queue job monitoring',
                    'Cache operations',
                    'Mail preview',
                    'Scheduled tasks',
                ],
                'environments' => ['local', 'staging'],
                'config' => [
                    'TELESCOPE_ENABLED' => 'true',
                    'TELESCOPE_DRIVER' => 'database',
                ],
                'notes' => 'Consider disabling in production for performance',
            ],
            'horizon' => [
                'package' => 'laravel/horizon',
                'description' => 'Queue monitoring dashboard for Redis queues',
                'use_cases' => [
                    'Queue metrics dashboard',
                    'Job failure tracking',
                    'Worker management',
                    'Queue balancing',
                    'Real-time monitoring',
                ],
                'requirements' => ['redis'],
                'environments' => ['all'],
                'notes' => 'Required for production queue management with Redis',
            ],
            'pulse' => [
                'package' => 'laravel/pulse',
                'description' => 'Real-time application performance monitoring',
                'use_cases' => [
                    'Server health metrics',
                    'Application performance',
                    'User activity tracking',
                    'Slow queries detection',
                    'Cache efficiency',
                ],
                'environments' => ['all'],
                'version' => '^1.0',
                'notes' => 'Lightweight alternative to commercial APM for Laravel 10+',
            ],
            'ray' => [
                'package' => 'spatie/laravel-ray',
                'description' => 'Desktop debugging application',
                'use_cases' => [
                    'Real-time variable inspection',
                    'Query debugging',
                    'Request tracing',
                    'Job debugging',
                ],
                'environments' => ['local'],
                'notes' => 'Development only - auto-disabled in production',
            ],
        ];
    }

    /**
     * Get error tracking services configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getErrorTracking(): array
    {
        return [
            'sentry' => [
                'package' => 'sentry/sentry-laravel',
                'description' => 'Error tracking and performance monitoring',
                'features' => [
                    'Exception tracking with stack traces',
                    'Performance monitoring (traces)',
                    'Release tracking',
                    'User feedback',
                    'Session replay',
                ],
                'config' => [
                    'SENTRY_LARAVEL_DSN' => 'your-dsn-here',
                    'SENTRY_TRACES_SAMPLE_RATE' => '0.1',
                ],
                'integration' => <<<'PHP'
                    // In config/sentry.php
                    return [
                        'dsn' => env('SENTRY_LARAVEL_DSN'),
                        'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
                        'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),
                        'send_default_pii' => false,
                    ];
                    PHP,
                'pricing' => 'Free tier available, then usage-based',
            ],
            'bugsnag' => [
                'package' => 'bugsnag/bugsnag-laravel',
                'description' => 'Application stability management',
                'features' => [
                    'Error monitoring',
                    'Stability scores',
                    'Release health',
                    'Breadcrumbs',
                ],
                'config' => [
                    'BUGSNAG_API_KEY' => 'your-api-key',
                ],
                'pricing' => 'Free tier available',
            ],
            'flare' => [
                'package' => 'spatie/laravel-ignition',
                'description' => 'Laravel-native error tracking by Spatie',
                'features' => [
                    'Beautiful error pages',
                    'Solution suggestions',
                    'Error sharing',
                    'AI-powered explanations',
                ],
                'config' => [
                    'FLARE_KEY' => 'your-key',
                ],
                'pricing' => 'Free error pages, paid tracking',
                'notes' => 'Best integration with Laravel ecosystem',
            ],
        ];
    }

    /**
     * Get metrics configuration recommendations.
     *
     * @return array<string, mixed>
     */
    public function getMetricsConfig(): array
    {
        return [
            'recommended_metrics' => [
                'application' => [
                    'request_duration_seconds' => [
                        'type' => 'histogram',
                        'description' => 'HTTP request duration in seconds',
                        'labels' => ['method', 'endpoint', 'status_code'],
                        'buckets' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
                    ],
                    'requests_total' => [
                        'type' => 'counter',
                        'description' => 'Total number of HTTP requests',
                        'labels' => ['method', 'endpoint', 'status_code'],
                    ],
                    'active_requests' => [
                        'type' => 'gauge',
                        'description' => 'Number of currently active requests',
                    ],
                ],
                'database' => [
                    'query_duration_seconds' => [
                        'type' => 'histogram',
                        'description' => 'Database query duration',
                        'labels' => ['operation', 'table'],
                    ],
                    'connections_active' => [
                        'type' => 'gauge',
                        'description' => 'Number of active database connections',
                    ],
                ],
                'queue' => [
                    'jobs_processed_total' => [
                        'type' => 'counter',
                        'description' => 'Total jobs processed',
                        'labels' => ['queue', 'job', 'status'],
                    ],
                    'job_duration_seconds' => [
                        'type' => 'histogram',
                        'description' => 'Job processing duration',
                        'labels' => ['queue', 'job'],
                    ],
                    'queue_size' => [
                        'type' => 'gauge',
                        'description' => 'Number of jobs in queue',
                        'labels' => ['queue'],
                    ],
                ],
                'cache' => [
                    'cache_hits_total' => [
                        'type' => 'counter',
                        'description' => 'Cache hit count',
                        'labels' => ['store'],
                    ],
                    'cache_misses_total' => [
                        'type' => 'counter',
                        'description' => 'Cache miss count',
                        'labels' => ['store'],
                    ],
                ],
                'business' => [
                    'users_active' => [
                        'type' => 'gauge',
                        'description' => 'Active users in last 5 minutes',
                    ],
                    'revenue_total' => [
                        'type' => 'counter',
                        'description' => 'Total revenue processed',
                        'labels' => ['currency', 'type'],
                    ],
                ],
            ],
            'alerting_rules' => $this->getAlertingRules(),
        ];
    }

    /**
     * Get health check configuration.
     *
     * @return array<string, mixed>
     */
    public function getHealthCheckConfig(): array
    {
        return [
            'package' => 'spatie/laravel-health',
            'description' => 'Health checks for Laravel applications',
            'checks' => [
                'database' => [
                    'class' => 'DatabaseCheck',
                    'critical' => true,
                ],
                'cache' => [
                    'class' => 'CacheCheck',
                    'critical' => true,
                ],
                'redis' => [
                    'class' => 'RedisCheck',
                    'critical' => $this->projectScale !== 'prototype',
                ],
                'queue' => [
                    'class' => 'QueueCheck',
                    'critical' => $this->projectScale !== 'prototype',
                    'config' => [
                        'failAfterMinutes' => 5,
                    ],
                ],
                'storage' => [
                    'class' => 'UsedDiskSpaceCheck',
                    'config' => [
                        'warnThreshold' => 70,
                        'errorThreshold' => 90,
                    ],
                ],
                'schedule' => [
                    'class' => 'ScheduleCheck',
                    'critical' => false,
                ],
                'horizon' => [
                    'class' => 'HorizonCheck',
                    'critical' => $this->hasRedisQueues(),
                ],
            ],
            'endpoints' => [
                'health' => '/health',
                'health_json' => '/health?format=json',
                'ready' => '/ready',
                'live' => '/live',
            ],
            'integration' => <<<'PHP'
                // In routes/web.php or api.php
                use Spatie\Health\Http\Controllers\HealthCheckResultsController;

                Route::get('health', HealthCheckResultsController::class);

                // In app/Providers/HealthServiceProvider.php
                use Spatie\Health\Facades\Health;
                use Spatie\Health\Checks\Checks\DatabaseCheck;
                use Spatie\Health\Checks\Checks\CacheCheck;
                use Spatie\Health\Checks\Checks\RedisCheck;

                Health::checks([
                    DatabaseCheck::new(),
                    CacheCheck::new(),
                    RedisCheck::new(),
                ]);
                PHP,
        ];
    }

    /**
     * Get logging configuration recommendations.
     *
     * @return array<string, mixed>
     */
    public function getLoggingConfig(): array
    {
        return [
            'structured_logging' => [
                'format' => 'json',
                'fields' => [
                    'timestamp',
                    'level',
                    'message',
                    'context',
                    'request_id',
                    'user_id',
                    'trace_id',
                    'span_id',
                ],
            ],
            'log_levels' => [
                'production' => 'warning',
                'staging' => 'info',
                'local' => 'debug',
            ],
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['daily', 'stderr'],
                    'ignore_exceptions' => false,
                ],
                'daily' => [
                    'driver' => 'daily',
                    'path' => 'storage/logs/laravel.log',
                    'level' => $this->environment === 'production' ? 'warning' : 'debug',
                    'days' => $this->getLogRetentionDays(),
                    'replace_placeholders' => true,
                ],
                'stderr' => [
                    'driver' => 'monolog',
                    'level' => 'debug',
                    'handler' => 'StreamHandler',
                    'with' => [
                        'stream' => 'php://stderr',
                    ],
                    'formatter' => 'JsonFormatter',
                ],
            ],
            'aggregation' => $this->getLogAggregationConfig(),
        ];
    }

    /**
     * Get tracing configuration.
     *
     * @return array<string, mixed>
     */
    public function getTracingConfig(): array
    {
        return [
            'enabled' => $this->projectScale !== 'prototype',
            'sample_rate' => match ($this->projectScale) {
                'prototype' => 0.0,
                'small' => 0.1,
                'medium' => 0.05,
                'large' => 0.01,
                'massive' => 0.001,
                default => 0.1,
            },
            'providers' => [
                'opentelemetry' => [
                    'package' => 'open-telemetry/opentelemetry-auto-laravel',
                    'description' => 'OpenTelemetry auto-instrumentation for Laravel',
                    'features' => ['Distributed tracing', 'Vendor neutral', 'Prometheus export'],
                ],
                'jaeger' => [
                    'description' => 'Jaeger distributed tracing',
                    'docker' => 'jaegertracing/all-in-one:latest',
                ],
                'zipkin' => [
                    'description' => 'Zipkin distributed tracing',
                    'docker' => 'openzipkin/zipkin:latest',
                ],
            ],
            'propagation' => [
                'headers' => [
                    'X-Request-ID',
                    'X-Trace-ID',
                    'X-Span-ID',
                    'traceparent',
                    'tracestate',
                ],
            ],
        ];
    }

    /**
     * Get alerting rules.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getAlertingRules(): array
    {
        return [
            'high_error_rate' => [
                'condition' => 'rate(http_requests_total{status_code=~"5.."}[5m]) / rate(http_requests_total[5m]) > 0.01',
                'severity' => 'critical',
                'description' => 'Error rate exceeds 1%',
            ],
            'high_latency' => [
                'condition' => 'histogram_quantile(0.95, rate(request_duration_seconds_bucket[5m])) > 1',
                'severity' => 'warning',
                'description' => 'P95 latency exceeds 1 second',
            ],
            'queue_backlog' => [
                'condition' => 'queue_size > 1000',
                'severity' => 'warning',
                'description' => 'Queue has more than 1000 pending jobs',
            ],
            'disk_space' => [
                'condition' => 'disk_used_percent > 80',
                'severity' => 'warning',
                'description' => 'Disk usage exceeds 80%',
            ],
            'database_connections' => [
                'condition' => 'db_connections_active > db_connections_max * 0.8',
                'severity' => 'warning',
                'description' => 'Database connection pool nearly exhausted',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPrototypeStack(): array
    {
        return [
            'logging' => [
                'driver' => 'daily',
                'aggregation' => 'none',
            ],
            'error_tracking' => [
                'provider' => 'flare',
                'reason' => 'Free with beautiful error pages',
            ],
            'apm' => [
                'provider' => 'none',
                'alternative' => 'Laravel Telescope for development',
            ],
            'metrics' => [
                'provider' => 'none',
            ],
            'health_checks' => [
                'enabled' => true,
                'checks' => ['database'],
            ],
            'cost' => 'Free',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSmallStack(): array
    {
        return [
            'logging' => [
                'driver' => 'daily',
                'aggregation' => 'betterstack',
                'reason' => 'Free tier with good retention',
            ],
            'error_tracking' => [
                'provider' => 'sentry',
                'reason' => 'Generous free tier, excellent Laravel integration',
            ],
            'apm' => [
                'provider' => 'pulse',
                'reason' => 'Free, Laravel-native, low overhead',
            ],
            'metrics' => [
                'provider' => 'pulse',
            ],
            'health_checks' => [
                'enabled' => true,
                'package' => 'spatie/laravel-health',
            ],
            'cost' => 'Free - $50/month',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getMediumStack(): array
    {
        return [
            'logging' => [
                'driver' => 'daily',
                'aggregation' => 'betterstack',
            ],
            'error_tracking' => [
                'provider' => 'sentry',
                'with_performance' => true,
            ],
            'apm' => [
                'primary' => 'pulse',
                'alternative' => 'sentry_performance',
            ],
            'metrics' => [
                'provider' => 'prometheus',
                'with' => 'grafana',
            ],
            'health_checks' => [
                'enabled' => true,
                'package' => 'spatie/laravel-health',
                'uptime_monitoring' => 'betterstack',
            ],
            'tracing' => [
                'enabled' => true,
                'sample_rate' => 0.05,
            ],
            'cost' => '$50 - $200/month',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getLargeStack(): array
    {
        return [
            'logging' => [
                'driver' => 'json',
                'aggregation' => 'datadog',
                'reason' => 'Unified observability platform',
            ],
            'error_tracking' => [
                'provider' => 'sentry',
                'tier' => 'team',
            ],
            'apm' => [
                'provider' => 'datadog',
                'with' => ['apm', 'profiling', 'rum'],
            ],
            'metrics' => [
                'provider' => 'datadog',
                'custom_metrics' => true,
            ],
            'health_checks' => [
                'enabled' => true,
                'external' => 'datadog_synthetics',
            ],
            'tracing' => [
                'enabled' => true,
                'provider' => 'datadog',
                'sample_rate' => 0.01,
            ],
            'alerting' => [
                'provider' => 'datadog',
                'pagerduty' => true,
            ],
            'cost' => '$500 - $2000/month',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getMassiveStack(): array
    {
        return [
            'logging' => [
                'driver' => 'json',
                'aggregation' => 'elk_or_datadog',
                'sampling' => true,
            ],
            'error_tracking' => [
                'provider' => 'sentry',
                'tier' => 'business',
            ],
            'apm' => [
                'provider' => 'datadog_or_newrelic',
                'full_stack' => true,
            ],
            'metrics' => [
                'provider' => 'prometheus_or_datadog',
                'federation' => true,
            ],
            'health_checks' => [
                'external' => true,
                'synthetic_monitoring' => true,
            ],
            'tracing' => [
                'enabled' => true,
                'distributed' => true,
                'sample_rate' => 0.001,
            ],
            'alerting' => [
                'provider' => 'multi',
                'on_call' => true,
                'runbooks' => true,
            ],
            'sre' => [
                'slos' => true,
                'error_budgets' => true,
            ],
            'cost' => '$2000+/month',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getLogAggregationConfig(): array
    {
        return match ($this->projectScale) {
            'prototype' => [
                'provider' => 'none',
                'notes' => 'Use local logs for development',
            ],
            'small' => [
                'provider' => 'betterstack',
                'reason' => 'Free tier, easy setup, log search',
                'config' => [
                    'LOGTAIL_SOURCE_TOKEN' => 'your-token',
                ],
            ],
            'medium' => [
                'provider' => 'betterstack',
                'alternatives' => ['papertrail', 'cloudwatch'],
            ],
            'large', 'massive' => [
                'provider' => 'datadog',
                'alternatives' => ['elk', 'splunk'],
            ],
            default => [
                'provider' => 'none',
            ],
        };
    }

    private function getLogRetentionDays(): int
    {
        return match ($this->projectScale) {
            'prototype' => 7,
            'small' => 14,
            'medium' => 30,
            'large' => 90,
            'massive' => 180,
            default => 14,
        };
    }

    private function hasRedisQueues(): bool
    {
        return in_array($this->projectScale, ['medium', 'large', 'massive'], true);
    }
}
