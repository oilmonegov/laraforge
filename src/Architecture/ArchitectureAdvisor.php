<?php

declare(strict_types=1);

namespace LaraForge\Architecture;

/**
 * Architecture Advisor
 *
 * Provides architecture recommendations based on project scale,
 * feature requirements, and best practices.
 */
final class ArchitectureAdvisor
{
    /**
     * Operation types for classification.
     */
    public const OP_TYPE_CRUD = 'crud';

    public const OP_TYPE_QUERY = 'query';

    public const OP_TYPE_COMMAND = 'command';

    public const OP_TYPE_EVENT = 'event';

    public const OP_TYPE_NOTIFICATION = 'notification';

    public const OP_TYPE_EXPORT = 'export';

    public const OP_TYPE_IMPORT = 'import';

    public const OP_TYPE_REPORT = 'report';

    public const OP_TYPE_INTEGRATION = 'integration';

    public const OP_TYPE_FILE = 'file';

    public function __construct(
        private readonly ProjectScale $scale,
    ) {}

    /**
     * Recommend sync or async for an operation.
     *
     * @param  array<string, mixed>  $context
     * @return array{mode: string, reason: string, implementation: array<string, mixed>}
     */
    public function recommendExecutionMode(string $operationType, array $context = []): array
    {
        /** @var int $estimatedDuration */
        $estimatedDuration = $context['estimated_duration_ms'] ?? $this->estimateDuration($operationType);
        /** @var bool $affectsUser */
        $affectsUser = $context['affects_user'] ?? true;
        /** @var bool $canRetry */
        $canRetry = $context['can_retry'] ?? true;
        /** @var string $dataVolume */
        $dataVolume = $context['data_volume'] ?? 'small';

        // Always async for certain operations
        $alwaysAsync = [
            self::OP_TYPE_NOTIFICATION,
            self::OP_TYPE_EXPORT,
            self::OP_TYPE_IMPORT,
            self::OP_TYPE_REPORT,
        ];

        if (in_array($operationType, $alwaysAsync, true)) {
            return $this->asyncRecommendation($operationType, 'Operation type should always be async');
        }

        // Check duration threshold
        $threshold = $this->scale->isSimple() ? 500 : 200;

        if ($estimatedDuration > $threshold) {
            return $this->asyncRecommendation($operationType, "Estimated duration ({$estimatedDuration}ms) exceeds threshold ({$threshold}ms)");
        }

        // Check data volume
        if ($dataVolume === 'large' || $dataVolume === 'massive') {
            return $this->asyncRecommendation($operationType, 'Large data volume requires async processing');
        }

        // External integrations
        if ($operationType === self::OP_TYPE_INTEGRATION) {
            if ($this->scale->requiresAsync()) {
                return $this->asyncRecommendation($operationType, 'External API calls should be async at this scale');
            }
        }

        // Default to sync for simple operations
        return [
            'mode' => 'sync',
            'reason' => 'Operation is fast enough for synchronous execution',
            'implementation' => [
                'approach' => 'direct',
                'pattern' => $this->getSyncPattern($operationType),
            ],
        ];
    }

    /**
     * Get recommended patterns for a feature.
     *
     * @return array<string, mixed>
     */
    public function recommendPatterns(string $featureType): array
    {
        $basePatterns = [
            'form_request' => 'Use FormRequest for validation',
            'api_resource' => 'Use API Resources for responses',
            'policy' => 'Use Policies for authorization',
        ];

        $patterns = match ($featureType) {
            'crud' => array_merge($basePatterns, [
                'repository' => $this->scale->isScalable() ? 'Consider Repository pattern' : 'Direct Eloquent is fine',
                'dto' => $this->scale->getExpectedRecords() > 100000 ? 'Use DTOs for data transfer' : 'Arrays are acceptable',
            ]),
            'reporting' => [
                'query_class' => 'Use dedicated Query classes',
                'caching' => $this->scale->requiresCaching() ? 'Cache report results' : 'Generate on demand',
                'async' => $this->scale->requiresAsync() ? 'Generate reports asynchronously' : 'Sync generation acceptable',
                'chunking' => 'Use chunking for large datasets',
            ],
            'integration' => [
                'circuit_breaker' => 'Implement circuit breaker pattern',
                'retry' => 'Use retry with exponential backoff',
                'queue' => $this->scale->requiresAsync() ? 'Queue external API calls' : 'Direct calls acceptable',
                'caching' => 'Cache API responses where appropriate',
            ],
            'notification' => [
                'queue' => 'Always queue notifications',
                'batching' => $this->scale->getConcurrentUsers() > 1000 ? 'Batch notifications' : 'Individual dispatch acceptable',
                'rate_limiting' => 'Implement rate limiting for external providers',
            ],
            'file_upload' => [
                'chunked' => $this->scale->getExpectedRecords() > 100000 ? 'Use chunked uploads' : 'Direct upload acceptable',
                'async_processing' => 'Process files asynchronously',
                'cdn' => $this->scale->requiresCaching() ? 'Serve via CDN' : 'Direct serving acceptable',
            ],
            default => $basePatterns,
        };

        return [
            'feature' => $featureType,
            'patterns' => $patterns,
            'scale_context' => [
                'tier' => $this->scale->getTier(),
                'mode' => $this->scale->getMode(),
            ],
        ];
    }

    /**
     * Analyze a feature and provide architecture guidance.
     *
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    public function analyzeFeature(array $feature): array
    {
        /** @var string $name */
        $name = $feature['name'] ?? 'Unknown';
        /** @var array<int, array<string, mixed>> $operations */
        $operations = $feature['operations'] ?? [];
        /** @var array<int, array<string, mixed>> $dataAccess */
        $dataAccess = $feature['data_access'] ?? [];
        /** @var array<int, array<string, mixed>> $integrations */
        $integrations = $feature['integrations'] ?? [];

        $analysis = [
            'feature' => $name,
            'operations' => [],
            'data_layer' => [],
            'integrations' => [],
            'recommendations' => [],
        ];

        // Analyze each operation
        foreach ($operations as $op) {
            /** @var string $opType */
            $opType = $op['type'] ?? self::OP_TYPE_CRUD;
            /** @var string $opName */
            $opName = $op['name'] ?? $opType;
            $analysis['operations'][$opName] = $this->recommendExecutionMode($opType, $op);
        }

        // Analyze data access patterns
        foreach ($dataAccess as $access) {
            $analysis['data_layer'][] = $this->analyzeDataAccess($access);
        }

        // Analyze integrations
        foreach ($integrations as $integration) {
            $analysis['integrations'][] = $this->analyzeIntegration($integration);
        }

        // Overall recommendations
        $analysis['recommendations'] = $this->generateRecommendations($analysis);

        return $analysis;
    }

    /**
     * Get FRD template with architecture sections.
     *
     * @return array<string, mixed>
     */
    public function getFrdArchitectureSections(): array
    {
        $scale = $this->scale;

        return [
            'performance_requirements' => [
                'response_time' => [
                    'api_p95' => $this->getTargetApiResponseTime(),
                    'page_load_p95' => $this->getTargetPageLoadTime(),
                    'background_job_p95' => '30s',
                ],
                'throughput' => [
                    'requests_per_minute' => $scale->getRequestsPerMinute(),
                    'concurrent_users' => $scale->getConcurrentUsers(),
                ],
                'availability' => $this->getTargetAvailability(),
            ],
            'scalability_requirements' => [
                'horizontal_scaling' => $scale->requiresHorizontalScaling(),
                'stateless_design' => $scale->requiresHorizontalScaling(),
                'session_handling' => $scale->requiresCaching() ? 'redis' : 'file',
                'load_balancing' => $scale->requiresHorizontalScaling() ? 'required' : 'optional',
            ],
            'data_requirements' => [
                'expected_volume' => $scale->getExpectedRecords(),
                'growth_rate' => 'Define expected growth rate',
                'retention' => $scale->getDataRetentionDays().' days',
                'backup' => $this->getBackupStrategy(),
            ],
            'caching_strategy' => $scale->getCachingRecommendations(),
            'queue_strategy' => $scale->getQueueRecommendations(),
            'infrastructure' => $scale->getInfrastructureRecommendations(),
        ];
    }

    /**
     * Convert PRD to FRD with architecture considerations.
     *
     * @param  array<string, mixed>  $prd
     * @return array<string, mixed>
     */
    public function prdToFrdGuidance(array $prd): array
    {
        /** @var array<int, array<string, mixed>> $features */
        $features = $prd['features'] ?? [];

        $frdGuidance = [
            'architecture_mode' => $this->scale->getMode(),
            'scale_tier' => $this->scale->getTier(),
            'architecture_sections' => $this->getFrdArchitectureSections(),
            'features' => [],
        ];

        foreach ($features as $feature) {
            /** @var string $featureName */
            $featureName = $feature['name'] ?? 'Unknown';
            /** @var string $featureType */
            $featureType = $feature['type'] ?? 'crud';
            $frdGuidance['features'][$featureName] = [
                'technical_approach' => $this->recommendPatterns($featureType),
                'data_model' => $this->recommendDataModel($feature),
                'api_design' => $this->recommendApiDesign($feature),
                'security' => $this->recommendSecurityMeasures($feature),
                'testing' => $this->recommendTestingStrategy($feature),
            ];
        }

        $frdGuidance['cross_cutting_concerns'] = [
            'logging' => $this->getLoggingStrategy(),
            'monitoring' => $this->getMonitoringStrategy(),
            'error_handling' => $this->getErrorHandlingStrategy(),
            'audit_trail' => $this->getAuditStrategy(),
        ];

        return $frdGuidance;
    }

    /**
     * Get AI context for architecture decisions.
     *
     * @return array<string, mixed>
     */
    public function getAiContext(): array
    {
        return [
            'scale' => $this->scale->getAiContext(),
            'sync_vs_async' => [
                'sync_threshold_ms' => $this->scale->isSimple() ? 500 : 200,
                'always_async' => ['email', 'notifications', 'exports', 'reports', 'file_processing'],
                'always_sync' => ['auth', 'validation', 'simple_reads'],
            ],
            'patterns' => [
                'simple_mode' => [
                    'Direct Eloquent models',
                    'Controller-based logic',
                    'Inline validation',
                    'Sync processing where possible',
                ],
                'scalable_mode' => [
                    'Repository pattern for data access',
                    'Action classes for business logic',
                    'Query classes for complex reads',
                    'Event-driven for side effects',
                    'Queue everything that can wait',
                ],
            ],
            'anti_patterns' => [
                'N+1 queries in loops',
                'Sync processing of bulk operations',
                'Missing indexes on foreign keys',
                'Hardcoded timeouts for external services',
                'No retry logic for external APIs',
            ],
        ];
    }

    /**
     * @return array{mode: string, reason: string, implementation: array<string, mixed>}
     */
    private function asyncRecommendation(string $operationType, string $reason): array
    {
        return [
            'mode' => 'async',
            'reason' => $reason,
            'implementation' => [
                'approach' => 'queue',
                'queue' => $this->getQueueForOperation($operationType),
                'pattern' => $this->getAsyncPattern($operationType),
                'retry' => $this->getRetryConfig($operationType),
            ],
        ];
    }

    private function estimateDuration(string $operationType): int
    {
        return match ($operationType) {
            self::OP_TYPE_CRUD => 50,
            self::OP_TYPE_QUERY => 100,
            self::OP_TYPE_COMMAND => 150,
            self::OP_TYPE_EVENT => 10,
            self::OP_TYPE_NOTIFICATION => 500,
            self::OP_TYPE_EXPORT => 5000,
            self::OP_TYPE_IMPORT => 10000,
            self::OP_TYPE_REPORT => 3000,
            self::OP_TYPE_INTEGRATION => 1000,
            self::OP_TYPE_FILE => 2000,
            default => 100,
        };
    }

    private function getSyncPattern(string $operationType): string
    {
        return match ($operationType) {
            self::OP_TYPE_CRUD => 'Controller -> FormRequest -> Model -> Resource',
            self::OP_TYPE_QUERY => 'Controller -> QueryClass -> Resource',
            self::OP_TYPE_COMMAND => 'Controller -> Action -> Event',
            default => 'Controller -> Service -> Response',
        };
    }

    private function getAsyncPattern(string $operationType): string
    {
        return match ($operationType) {
            self::OP_TYPE_NOTIFICATION => 'Notification::send() with ShouldQueue',
            self::OP_TYPE_EXPORT => 'ExportJob dispatched, download link emailed',
            self::OP_TYPE_IMPORT => 'ImportJob with progress tracking',
            self::OP_TYPE_REPORT => 'ReportJob with caching',
            self::OP_TYPE_INTEGRATION => 'Job with circuit breaker',
            default => 'Dispatch job, return accepted response',
        };
    }

    private function getQueueForOperation(string $operationType): string
    {
        return match ($operationType) {
            self::OP_TYPE_NOTIFICATION => 'notifications',
            self::OP_TYPE_EXPORT, self::OP_TYPE_IMPORT => 'exports',
            self::OP_TYPE_REPORT => 'reports',
            self::OP_TYPE_INTEGRATION => 'integrations',
            default => 'default',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getRetryConfig(string $operationType): array
    {
        return match ($operationType) {
            self::OP_TYPE_NOTIFICATION => ['tries' => 3, 'backoff' => [60, 300, 900]],
            self::OP_TYPE_INTEGRATION => ['tries' => 5, 'backoff' => [10, 30, 60, 120, 300]],
            self::OP_TYPE_EXPORT, self::OP_TYPE_IMPORT => ['tries' => 2, 'backoff' => [300]],
            default => ['tries' => 3, 'backoff' => [60, 180, 600]],
        };
    }

    /**
     * @param  array<string, mixed>  $access
     * @return array<string, mixed>
     */
    private function analyzeDataAccess(array $access): array
    {
        $type = $access['type'] ?? 'read';
        $volume = $access['volume'] ?? 'small';

        $recommendations = [];

        if ($volume === 'large' || $volume === 'massive') {
            $recommendations[] = 'Use chunking for iteration';
            $recommendations[] = 'Consider cursor-based pagination';
        }

        if ($type === 'write' && $this->scale->requiresAsync()) {
            $recommendations[] = 'Consider queuing bulk writes';
        }

        if ($type === 'read' && $this->scale->requiresCaching()) {
            $recommendations[] = 'Implement caching layer';
        }

        return [
            'access_type' => $type,
            'volume' => $volume,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @param  array<string, mixed>  $integration
     * @return array<string, mixed>
     */
    private function analyzeIntegration(array $integration): array
    {
        return [
            'name' => $integration['name'] ?? 'External Service',
            'type' => $integration['type'] ?? 'api',
            'recommendations' => [
                'Use circuit breaker pattern',
                'Implement retry with backoff',
                $this->scale->requiresAsync() ? 'Queue API calls' : 'Sync acceptable for simple calls',
                'Cache responses where applicable',
                'Monitor response times and error rates',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string>
     */
    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Check for async operations
        /** @var array<string, array<string, mixed>> $operations */
        $operations = $analysis['operations'] ?? [];
        $asyncOps = array_filter(
            $operations,
            fn (array $op): bool => ($op['mode'] ?? '') === 'async'
        );

        if (! empty($asyncOps)) {
            $recommendations[] = 'Configure queue workers for: '.implode(', ', array_keys($asyncOps));
        }

        // Check for caching needs
        if ($this->scale->requiresCaching()) {
            $recommendations[] = 'Implement Redis caching for frequently accessed data';
        }

        // Check for integrations
        /** @var array<int, array<string, mixed>> $integrations */
        $integrations = $analysis['integrations'] ?? [];
        if (! empty($integrations)) {
            $recommendations[] = 'Implement circuit breakers for external integrations';
        }

        return $recommendations;
    }

    private function getTargetApiResponseTime(): string
    {
        return match ($this->scale->getTier()) {
            ProjectScale::TIER_PROTOTYPE => '500ms',
            ProjectScale::TIER_SMALL => '300ms',
            ProjectScale::TIER_MEDIUM => '200ms',
            default => '100ms',
        };
    }

    private function getTargetPageLoadTime(): string
    {
        return match ($this->scale->getTier()) {
            ProjectScale::TIER_PROTOTYPE => '3s',
            ProjectScale::TIER_SMALL => '2s',
            default => '1s',
        };
    }

    private function getTargetAvailability(): string
    {
        return match ($this->scale->getTier()) {
            ProjectScale::TIER_PROTOTYPE => '95%',
            ProjectScale::TIER_SMALL => '99%',
            ProjectScale::TIER_MEDIUM => '99.5%',
            default => '99.9%',
        };
    }

    private function getBackupStrategy(): string
    {
        return match ($this->scale->getTier()) {
            ProjectScale::TIER_PROTOTYPE, ProjectScale::TIER_SMALL => 'Daily automated backups',
            ProjectScale::TIER_MEDIUM => 'Hourly automated backups with 30-day retention',
            default => 'Continuous replication with point-in-time recovery',
        };
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function recommendDataModel(array $feature): array
    {
        return [
            'indexing' => 'Index all foreign keys and frequently queried columns',
            'relationships' => 'Define eager loading for common access patterns',
            'timestamps' => 'Use timestamps for audit trail',
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function recommendApiDesign(array $feature): array
    {
        return [
            'versioning' => 'Use URL versioning (v1, v2)',
            'pagination' => 'Cursor-based for large datasets, offset for small',
            'filtering' => 'Support query parameters for filtering',
            'response_format' => 'Use API Resources for consistent structure',
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function recommendSecurityMeasures(array $feature): array
    {
        return [
            'authentication' => 'Sanctum for API, session for web',
            'authorization' => 'Policy-based authorization',
            'validation' => 'FormRequest with strict rules',
            'rate_limiting' => $this->scale->requiresAsync() ? 'Implement rate limiting' : 'Basic throttling',
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function recommendTestingStrategy(array $feature): array
    {
        return [
            'unit' => 'Test business logic in isolation',
            'feature' => 'Test HTTP endpoints and flows',
            'integration' => 'Test external service integrations',
            'coverage' => '80% minimum',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getLoggingStrategy(): array
    {
        return match ($this->scale->getTier()) {
            ProjectScale::TIER_PROTOTYPE => ['driver' => 'single', 'level' => 'debug'],
            ProjectScale::TIER_SMALL => ['driver' => 'daily', 'level' => 'info'],
            default => ['driver' => 'stack', 'channels' => ['daily', 'slack'], 'level' => 'warning'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getMonitoringStrategy(): array
    {
        return match ($this->scale->getTier()) {
            ProjectScale::TIER_PROTOTYPE => ['basic' => 'Laravel logs'],
            ProjectScale::TIER_SMALL => ['error_tracking' => 'Sentry/Bugsnag'],
            default => ['apm' => true, 'distributed_tracing' => true, 'alerting' => true],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getErrorHandlingStrategy(): array
    {
        return [
            'exceptions' => 'Custom exception handler with proper HTTP responses',
            'validation' => 'Return 422 with structured error messages',
            'not_found' => 'Return 404 with helpful message',
            'server_error' => 'Log full trace, return generic message to user',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAuditStrategy(): array
    {
        return match ($this->scale->getTier()) {
            ProjectScale::TIER_PROTOTYPE => ['enabled' => false],
            ProjectScale::TIER_SMALL => ['models' => ['User'], 'retention' => 30],
            default => ['models' => 'all_sensitive', 'retention' => 365, 'async' => true],
        };
    }

    /**
     * Create from project scale.
     */
    public static function fromScale(ProjectScale $scale): self
    {
        return new self($scale);
    }
}
