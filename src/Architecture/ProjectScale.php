<?php

declare(strict_types=1);

namespace LaraForge\Architecture;

/**
 * Project Scale
 *
 * Defines expected project size and scale requirements.
 * Used to inform architecture decisions, PRD/FRD generation,
 * and infrastructure recommendations.
 */
final class ProjectScale
{
    /**
     * Scale tiers.
     */
    public const TIER_PROTOTYPE = 'prototype';     // < 100 users, learning/MVP

    public const TIER_SMALL = 'small';             // 100-1K users, small business

    public const TIER_MEDIUM = 'medium';           // 1K-100K users, growing business

    public const TIER_LARGE = 'large';             // 100K-1M users, enterprise

    public const TIER_MASSIVE = 'massive';         // 1M+ users, high-scale

    /**
     * Architecture modes.
     */
    public const MODE_SIMPLE = 'simple';           // Monolith, sync, minimal infra

    public const MODE_BALANCED = 'balanced';       // Monolith with queues, caching

    public const MODE_SCALABLE = 'scalable';       // Microservices-ready, async-first

    /**
     * @var array<string, array<string, mixed>>
     */
    private const TIER_DEFAULTS = [
        self::TIER_PROTOTYPE => [
            'expected_users' => 100,
            'expected_records' => 10000,
            'concurrent_users' => 10,
            'requests_per_minute' => 100,
            'data_retention_days' => 30,
            'recommended_mode' => self::MODE_SIMPLE,
        ],
        self::TIER_SMALL => [
            'expected_users' => 1000,
            'expected_records' => 100000,
            'concurrent_users' => 100,
            'requests_per_minute' => 1000,
            'data_retention_days' => 90,
            'recommended_mode' => self::MODE_SIMPLE,
        ],
        self::TIER_MEDIUM => [
            'expected_users' => 100000,
            'expected_records' => 10000000,
            'concurrent_users' => 5000,
            'requests_per_minute' => 50000,
            'data_retention_days' => 365,
            'recommended_mode' => self::MODE_BALANCED,
        ],
        self::TIER_LARGE => [
            'expected_users' => 1000000,
            'expected_records' => 100000000,
            'concurrent_users' => 50000,
            'requests_per_minute' => 500000,
            'data_retention_days' => 730,
            'recommended_mode' => self::MODE_SCALABLE,
        ],
        self::TIER_MASSIVE => [
            'expected_users' => 10000000,
            'expected_records' => 1000000000,
            'concurrent_users' => 500000,
            'requests_per_minute' => 5000000,
            'data_retention_days' => 1095,
            'recommended_mode' => self::MODE_SCALABLE,
        ],
    ];

    /**
     * @param  array<string, mixed>  $customMetrics
     */
    public function __construct(
        private readonly string $tier = self::TIER_SMALL,
        private readonly string $mode = self::MODE_SIMPLE,
        private readonly int $expectedUsers = 1000,
        private readonly int $expectedRecords = 100000,
        private readonly int $concurrentUsers = 100,
        private readonly int $requestsPerMinute = 1000,
        private readonly int $dataRetentionDays = 90,
        private readonly array $customMetrics = [],
    ) {}

    public function getTier(): string
    {
        return $this->tier;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getExpectedUsers(): int
    {
        return $this->expectedUsers;
    }

    public function getExpectedRecords(): int
    {
        return $this->expectedRecords;
    }

    public function getConcurrentUsers(): int
    {
        return $this->concurrentUsers;
    }

    public function getRequestsPerMinute(): int
    {
        return $this->requestsPerMinute;
    }

    public function getDataRetentionDays(): int
    {
        return $this->dataRetentionDays;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomMetrics(): array
    {
        return $this->customMetrics;
    }

    /**
     * Check if project requires async processing.
     */
    public function requiresAsync(): bool
    {
        return $this->mode !== self::MODE_SIMPLE
            || $this->concurrentUsers > 500
            || $this->requestsPerMinute > 5000;
    }

    /**
     * Check if project requires caching layer.
     */
    public function requiresCaching(): bool
    {
        return $this->mode !== self::MODE_SIMPLE
            || $this->concurrentUsers > 100
            || $this->expectedRecords > 100000;
    }

    /**
     * Check if project requires read replicas.
     */
    public function requiresReadReplicas(): bool
    {
        return $this->tier === self::TIER_LARGE
            || $this->tier === self::TIER_MASSIVE
            || $this->requestsPerMinute > 100000;
    }

    /**
     * Check if project requires horizontal scaling.
     */
    public function requiresHorizontalScaling(): bool
    {
        return $this->mode === self::MODE_SCALABLE
            || $this->concurrentUsers > 10000;
    }

    /**
     * Check if project should use simple architecture.
     */
    public function isSimple(): bool
    {
        return $this->mode === self::MODE_SIMPLE;
    }

    /**
     * Check if project should prioritize scalability.
     */
    public function isScalable(): bool
    {
        return $this->mode === self::MODE_SCALABLE;
    }

    /**
     * Get database recommendations.
     *
     * @return array<string, mixed>
     */
    public function getDatabaseRecommendations(): array
    {
        $recommendations = [
            'indexing' => 'Required for all foreign keys and frequently queried columns',
            'query_optimization' => true,
        ];

        if ($this->expectedRecords > 1000000) {
            $recommendations['partitioning'] = 'Consider table partitioning for large tables';
            $recommendations['archiving'] = 'Implement data archiving strategy';
        }

        if ($this->requiresReadReplicas()) {
            $recommendations['read_replicas'] = 'Use read replicas for read-heavy operations';
            $recommendations['connection_pooling'] = 'Implement connection pooling';
        }

        if ($this->tier === self::TIER_MASSIVE) {
            $recommendations['sharding'] = 'Consider database sharding';
            $recommendations['cqrs'] = 'Implement CQRS for read/write separation';
        }

        return $recommendations;
    }

    /**
     * Get caching recommendations.
     *
     * @return array<string, mixed>
     */
    public function getCachingRecommendations(): array
    {
        if (! $this->requiresCaching()) {
            return ['strategy' => 'file', 'note' => 'File caching sufficient for this scale'];
        }

        $recommendations = [
            'driver' => 'redis',
            'strategies' => [],
        ];

        // Query caching
        if ($this->expectedRecords > 10000) {
            $recommendations['strategies']['query_cache'] = [
                'enabled' => true,
                'ttl' => 3600,
                'tags' => true,
            ];
        }

        // Page/response caching
        if ($this->requestsPerMinute > 10000) {
            $recommendations['strategies']['response_cache'] = [
                'enabled' => true,
                'ttl' => 60,
                'vary_by' => ['user', 'query'],
            ];
        }

        // Session handling
        if ($this->concurrentUsers > 1000) {
            $recommendations['strategies']['session'] = [
                'driver' => 'redis',
                'lifetime' => 120,
            ];
        }

        return $recommendations;
    }

    /**
     * Get queue recommendations.
     *
     * @return array<string, mixed>
     */
    public function getQueueRecommendations(): array
    {
        if (! $this->requiresAsync()) {
            return [
                'driver' => 'sync',
                'note' => 'Synchronous processing acceptable for this scale',
            ];
        }

        $recommendations = [
            'driver' => 'redis',
            'queues' => [
                'default' => ['workers' => 2, 'timeout' => 60],
            ],
        ];

        if ($this->tier === self::TIER_MEDIUM || $this->tier === self::TIER_LARGE) {
            $recommendations['queues']['high'] = ['workers' => 4, 'timeout' => 30];
            $recommendations['queues']['low'] = ['workers' => 1, 'timeout' => 300];
        }

        if ($this->tier === self::TIER_LARGE || $this->tier === self::TIER_MASSIVE) {
            $recommendations['queues']['notifications'] = ['workers' => 2, 'timeout' => 60];
            $recommendations['queues']['exports'] = ['workers' => 1, 'timeout' => 600];
            $recommendations['horizon'] = true;
            $recommendations['rate_limiting'] = true;
        }

        return $recommendations;
    }

    /**
     * Get analytics/dashboard recommendations.
     *
     * @return array<string, mixed>
     */
    public function getAnalyticsRecommendations(): array
    {
        $recommendations = [
            'real_time' => false,
            'aggregation' => 'daily',
            'retention' => $this->dataRetentionDays,
        ];

        if ($this->tier === self::TIER_PROTOTYPE || $this->tier === self::TIER_SMALL) {
            $recommendations['approach'] = 'inline';
            $recommendations['note'] = 'Calculate analytics inline - low data volume';
        } elseif ($this->tier === self::TIER_MEDIUM) {
            $recommendations['approach'] = 'scheduled';
            $recommendations['aggregation'] = 'hourly';
            $recommendations['note'] = 'Use scheduled jobs for aggregation';
        } else {
            $recommendations['approach'] = 'stream';
            $recommendations['real_time'] = true;
            $recommendations['aggregation'] = 'real-time with hourly snapshots';
            $recommendations['note'] = 'Consider dedicated analytics service or data warehouse';
            $recommendations['consider'] = ['ClickHouse', 'TimescaleDB', 'BigQuery'];
        }

        return $recommendations;
    }

    /**
     * Get infrastructure recommendations.
     *
     * @return array<string, mixed>
     */
    public function getInfrastructureRecommendations(): array
    {
        return match ($this->tier) {
            self::TIER_PROTOTYPE => [
                'hosting' => 'Single server or PaaS (Forge, Railway)',
                'database' => 'Single MySQL/PostgreSQL instance',
                'cache' => 'File or SQLite',
                'storage' => 'Local or single S3 bucket',
                'cdn' => 'Optional',
                'monitoring' => 'Basic logging',
            ],
            self::TIER_SMALL => [
                'hosting' => 'Single server with room to scale',
                'database' => 'Managed MySQL/PostgreSQL',
                'cache' => 'Redis (single instance)',
                'storage' => 'S3 with CloudFront',
                'cdn' => 'Recommended for assets',
                'monitoring' => 'Application monitoring (Sentry, Bugsnag)',
            ],
            self::TIER_MEDIUM => [
                'hosting' => 'Load-balanced servers or container orchestration',
                'database' => 'Managed DB with read replica',
                'cache' => 'Redis cluster',
                'storage' => 'S3 with CDN',
                'cdn' => 'Required',
                'monitoring' => 'Full observability stack',
                'queue' => 'Dedicated queue workers',
            ],
            self::TIER_LARGE => [
                'hosting' => 'Kubernetes or auto-scaling groups',
                'database' => 'Multi-AZ with read replicas',
                'cache' => 'ElastiCache/Redis cluster',
                'storage' => 'Multi-region S3',
                'cdn' => 'Global CDN',
                'monitoring' => 'Distributed tracing, APM',
                'queue' => 'Horizon with multiple supervisors',
                'search' => 'Elasticsearch/Meilisearch',
            ],
            self::TIER_MASSIVE => [
                'hosting' => 'Multi-region Kubernetes',
                'database' => 'Sharded or distributed database',
                'cache' => 'Distributed cache layer',
                'storage' => 'Global object storage',
                'cdn' => 'Multi-CDN strategy',
                'monitoring' => 'Comprehensive observability platform',
                'queue' => 'Distributed message queue (SQS, RabbitMQ)',
                'search' => 'Dedicated search cluster',
                'analytics' => 'Dedicated data warehouse',
            ],
            default => [],
        };
    }

    /**
     * Get operation classification (sync vs async).
     *
     * @return array<string, array<string, string>>
     */
    public function getOperationClassification(): array
    {
        $sync = [
            'auth' => 'Authentication/authorization checks',
            'validation' => 'Input validation',
            'simple_crud' => 'Basic CRUD under 100ms',
            'cache_reads' => 'Reading from cache',
        ];

        $async = [];

        // Add async operations based on scale
        if ($this->requiresAsync()) {
            $async = array_merge($async, [
                'email' => 'Email sending',
                'notifications' => 'Push notifications',
                'webhooks' => 'Webhook dispatching',
                'file_processing' => 'File uploads and processing',
            ]);
        }

        if ($this->tier !== self::TIER_PROTOTYPE && $this->tier !== self::TIER_SMALL) {
            $async = array_merge($async, [
                'reports' => 'Report generation',
                'exports' => 'Data exports (CSV, PDF)',
                'bulk_operations' => 'Bulk updates/deletes',
                'third_party_apis' => 'External API calls',
                'search_indexing' => 'Search index updates',
            ]);
        }

        if ($this->tier === self::TIER_LARGE || $this->tier === self::TIER_MASSIVE) {
            $async = array_merge($async, [
                'analytics' => 'Analytics aggregation',
                'audit_logs' => 'Audit log writing',
                'cache_warming' => 'Cache warming',
                'cleanup_jobs' => 'Data cleanup and archival',
            ]);
        }

        return [
            'synchronous' => $sync,
            'asynchronous' => $async,
        ];
    }

    /**
     * Get PRD/FRD guidance based on scale.
     *
     * @return array<string, mixed>
     */
    public function getPrdFrdGuidance(): array
    {
        return [
            'scale_considerations' => [
                'expected_load' => [
                    'users' => $this->expectedUsers,
                    'records' => $this->expectedRecords,
                    'concurrent' => $this->concurrentUsers,
                    'rpm' => $this->requestsPerMinute,
                ],
                'architecture_mode' => $this->mode,
                'async_required' => $this->requiresAsync(),
                'caching_required' => $this->requiresCaching(),
            ],
            'frd_sections' => [
                'performance_requirements' => [
                    'response_time_p95' => $this->getTargetResponseTime(),
                    'throughput' => $this->requestsPerMinute,
                    'availability' => $this->getTargetAvailability(),
                ],
                'data_requirements' => [
                    'volume' => $this->expectedRecords,
                    'retention' => $this->dataRetentionDays,
                    'backup_frequency' => $this->getBackupFrequency(),
                ],
                'scalability_requirements' => [
                    'horizontal_scaling' => $this->requiresHorizontalScaling(),
                    'read_replicas' => $this->requiresReadReplicas(),
                    'auto_scaling' => $this->tier !== self::TIER_PROTOTYPE && $this->tier !== self::TIER_SMALL,
                ],
            ],
            'implementation_notes' => $this->getImplementationNotes(),
        ];
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier,
            'mode' => $this->mode,
            'expected_users' => $this->expectedUsers,
            'expected_records' => $this->expectedRecords,
            'concurrent_users' => $this->concurrentUsers,
            'requests_per_minute' => $this->requestsPerMinute,
            'data_retention_days' => $this->dataRetentionDays,
            'custom_metrics' => $this->customMetrics,
            'requires_async' => $this->requiresAsync(),
            'requires_caching' => $this->requiresCaching(),
            'requires_read_replicas' => $this->requiresReadReplicas(),
            'requires_horizontal_scaling' => $this->requiresHorizontalScaling(),
        ];
    }

    /**
     * Get AI context for architecture decisions.
     *
     * @return array<string, mixed>
     */
    public function getAiContext(): array
    {
        return [
            'scale' => $this->toArray(),
            'operation_classification' => $this->getOperationClassification(),
            'database' => $this->getDatabaseRecommendations(),
            'caching' => $this->getCachingRecommendations(),
            'queues' => $this->getQueueRecommendations(),
            'analytics' => $this->getAnalyticsRecommendations(),
            'infrastructure' => $this->getInfrastructureRecommendations(),
            'patterns' => [
                'Use queues for: '.implode(', ', array_keys($this->getOperationClassification()['asynchronous'])),
                'Keep synchronous: '.implode(', ', array_keys($this->getOperationClassification()['synchronous'])),
                $this->requiresCaching() ? 'Implement caching layer' : 'File caching sufficient',
                $this->requiresReadReplicas() ? 'Use read replicas for heavy reads' : 'Single database sufficient',
            ],
        ];
    }

    private function getTargetResponseTime(): string
    {
        return match ($this->tier) {
            self::TIER_PROTOTYPE => '500ms',
            self::TIER_SMALL => '300ms',
            self::TIER_MEDIUM => '200ms',
            self::TIER_LARGE => '100ms',
            self::TIER_MASSIVE => '50ms',
            default => '300ms',
        };
    }

    private function getTargetAvailability(): string
    {
        return match ($this->tier) {
            self::TIER_PROTOTYPE => '95%',
            self::TIER_SMALL => '99%',
            self::TIER_MEDIUM => '99.5%',
            self::TIER_LARGE => '99.9%',
            self::TIER_MASSIVE => '99.99%',
            default => '99%',
        };
    }

    private function getBackupFrequency(): string
    {
        return match ($this->tier) {
            self::TIER_PROTOTYPE => 'daily',
            self::TIER_SMALL => 'daily',
            self::TIER_MEDIUM => 'hourly',
            self::TIER_LARGE => 'continuous',
            self::TIER_MASSIVE => 'continuous with point-in-time recovery',
            default => 'daily',
        };
    }

    /**
     * @return array<string>
     */
    private function getImplementationNotes(): array
    {
        $notes = [];

        if ($this->isSimple()) {
            $notes[] = 'Keep architecture simple - avoid premature optimization';
            $notes[] = 'Use synchronous processing where possible';
            $notes[] = 'Single database is sufficient';
        }

        if ($this->mode === self::MODE_BALANCED) {
            $notes[] = 'Use queues for email, notifications, and file processing';
            $notes[] = 'Implement Redis caching for frequently accessed data';
            $notes[] = 'Consider read replica for reporting queries';
        }

        if ($this->isScalable()) {
            $notes[] = 'Design for horizontal scaling from the start';
            $notes[] = 'Use event-driven architecture where applicable';
            $notes[] = 'Implement proper cache invalidation strategies';
            $notes[] = 'Consider CQRS for complex domains';
            $notes[] = 'Use distributed tracing for debugging';
        }

        return $notes;
    }

    /**
     * Create from tier with defaults.
     */
    public static function fromTier(string $tier): self
    {
        $defaults = self::TIER_DEFAULTS[$tier] ?? self::TIER_DEFAULTS[self::TIER_SMALL];

        /** @var string $mode */
        $mode = $defaults['recommended_mode'];
        /** @var int $expectedUsers */
        $expectedUsers = $defaults['expected_users'];
        /** @var int $expectedRecords */
        $expectedRecords = $defaults['expected_records'];
        /** @var int $concurrentUsers */
        $concurrentUsers = $defaults['concurrent_users'];
        /** @var int $requestsPerMinute */
        $requestsPerMinute = $defaults['requests_per_minute'];
        /** @var int $dataRetentionDays */
        $dataRetentionDays = $defaults['data_retention_days'];

        return new self(
            tier: $tier,
            mode: $mode,
            expectedUsers: $expectedUsers,
            expectedRecords: $expectedRecords,
            concurrentUsers: $concurrentUsers,
            requestsPerMinute: $requestsPerMinute,
            dataRetentionDays: $dataRetentionDays,
        );
    }

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var string $tier */
        $tier = $data['tier'] ?? self::TIER_SMALL;
        /** @var string $mode */
        $mode = $data['mode'] ?? self::MODE_SIMPLE;
        /** @var int $expectedUsers */
        $expectedUsers = $data['expected_users'] ?? 1000;
        /** @var int $expectedRecords */
        $expectedRecords = $data['expected_records'] ?? 100000;
        /** @var int $concurrentUsers */
        $concurrentUsers = $data['concurrent_users'] ?? 100;
        /** @var int $requestsPerMinute */
        $requestsPerMinute = $data['requests_per_minute'] ?? 1000;
        /** @var int $dataRetentionDays */
        $dataRetentionDays = $data['data_retention_days'] ?? 90;
        /** @var array<string, mixed> $customMetrics */
        $customMetrics = $data['custom_metrics'] ?? [];

        return new self(
            tier: $tier,
            mode: $mode,
            expectedUsers: $expectedUsers,
            expectedRecords: $expectedRecords,
            concurrentUsers: $concurrentUsers,
            requestsPerMinute: $requestsPerMinute,
            dataRetentionDays: $dataRetentionDays,
            customMetrics: $customMetrics,
        );
    }
}
