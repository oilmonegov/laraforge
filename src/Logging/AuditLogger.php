<?php

declare(strict_types=1);

namespace LaraForge\Logging;

use DateTimeImmutable;

/**
 * Audit Logger (AAA - Authentication, Authorization, Accounting)
 *
 * Comprehensive logging for security and compliance.
 * Logs everything while filtering sensitive data.
 * Supports daily rotation and scale-aware configuration.
 */
final class AuditLogger
{
    /**
     * Log categories.
     */
    public const CATEGORY_AUTH = 'authentication';

    public const CATEGORY_AUTHZ = 'authorization';

    public const CATEGORY_ACCOUNTING = 'accounting';

    public const CATEGORY_DATA_ACCESS = 'data_access';

    public const CATEGORY_SECURITY = 'security';

    public const CATEGORY_SYSTEM = 'system';

    public const CATEGORY_API = 'api';

    public const CATEGORY_USER_ACTION = 'user_action';

    /**
     * Log levels.
     */
    public const LEVEL_DEBUG = 'debug';

    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_ERROR = 'error';

    public const LEVEL_CRITICAL = 'critical';

    /**
     * Fields to mask in logs.
     *
     * @var array<string>
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'secret',
        'api_key',
        'api_secret',
        'token',
        'access_token',
        'refresh_token',
        'bearer',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'expiry',
        'ssn',
        'social_security',
        'private_key',
        'encryption_key',
        'stripe_key',
        'aws_secret',
        'database_password',
        'db_password',
        'remember_token',
        'two_factor_secret',
        'recovery_codes',
    ];

    /**
     * @var array<array<string, mixed>>
     */
    private array $buffer = [];

    private int $bufferSize = 100;

    /**
     * @var callable|null
     */
    private $writer;

    public function __construct(
        private readonly string $logPath,
        private readonly string $projectScale = 'small',
        ?callable $writer = null,
    ) {
        $this->writer = $writer;
        $this->configureForScale();
    }

    /**
     * Log authentication event.
     *
     * @param  array<string, mixed>  $context
     */
    public function logAuth(
        string $event,
        ?string $userId = null,
        array $context = [],
        string $level = self::LEVEL_INFO,
    ): void {
        $this->log(
            category: self::CATEGORY_AUTH,
            event: $event,
            context: array_merge($context, [
                'user_id' => $userId,
                'ip_address' => $context['ip_address'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
            ]),
            level: $level,
        );
    }

    /**
     * Log authorization event.
     *
     * @param  array<string, mixed>  $context
     */
    public function logAuthz(
        string $event,
        ?string $userId = null,
        string $resource = '',
        string $action = '',
        bool $allowed = true,
        array $context = [],
    ): void {
        $this->log(
            category: self::CATEGORY_AUTHZ,
            event: $event,
            context: array_merge($context, [
                'user_id' => $userId,
                'resource' => $resource,
                'action' => $action,
                'allowed' => $allowed,
            ]),
            level: $allowed ? self::LEVEL_INFO : self::LEVEL_WARNING,
        );
    }

    /**
     * Log accounting/activity event.
     *
     * @param  array<string, mixed>  $context
     */
    public function logAccounting(
        string $event,
        ?string $userId = null,
        array $context = [],
    ): void {
        $this->log(
            category: self::CATEGORY_ACCOUNTING,
            event: $event,
            context: array_merge($context, [
                'user_id' => $userId,
            ]),
            level: self::LEVEL_INFO,
        );
    }

    /**
     * Log data access event.
     *
     * @param  array<string, mixed>  $context
     */
    public function logDataAccess(
        string $event,
        string $model,
        string|int|null $modelId = null,
        ?string $userId = null,
        string $action = 'read',
        array $context = [],
    ): void {
        $this->log(
            category: self::CATEGORY_DATA_ACCESS,
            event: $event,
            context: array_merge($context, [
                'model' => $model,
                'model_id' => $modelId,
                'user_id' => $userId,
                'action' => $action,
            ]),
            level: self::LEVEL_INFO,
        );
    }

    /**
     * Log security event.
     *
     * @param  array<string, mixed>  $context
     */
    public function logSecurity(
        string $event,
        array $context = [],
        string $level = self::LEVEL_WARNING,
    ): void {
        $this->log(
            category: self::CATEGORY_SECURITY,
            event: $event,
            context: $context,
            level: $level,
        );
    }

    /**
     * Log API request/response.
     *
     * @param  array<string, mixed>  $context
     */
    public function logApi(
        string $method,
        string $endpoint,
        int $statusCode,
        float $duration,
        ?string $userId = null,
        array $context = [],
    ): void {
        $this->log(
            category: self::CATEGORY_API,
            event: "{$method} {$endpoint}",
            context: array_merge($context, [
                'method' => $method,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'user_id' => $userId,
            ]),
            level: $statusCode >= 500 ? self::LEVEL_ERROR : self::LEVEL_INFO,
        );
    }

    /**
     * Log user action.
     *
     * @param  array<string, mixed>  $context
     */
    public function logUserAction(
        string $action,
        ?string $userId = null,
        array $context = [],
    ): void {
        $this->log(
            category: self::CATEGORY_USER_ACTION,
            event: $action,
            context: array_merge($context, [
                'user_id' => $userId,
            ]),
            level: self::LEVEL_INFO,
        );
    }

    /**
     * Log system event.
     *
     * @param  array<string, mixed>  $context
     */
    public function logSystem(
        string $event,
        array $context = [],
        string $level = self::LEVEL_INFO,
    ): void {
        $this->log(
            category: self::CATEGORY_SYSTEM,
            event: $event,
            context: $context,
            level: $level,
        );
    }

    /**
     * Generic log method.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $category,
        string $event,
        array $context = [],
        string $level = self::LEVEL_INFO,
    ): void {
        $entry = [
            'timestamp' => (new DateTimeImmutable)->format('c'),
            'category' => $category,
            'level' => $level,
            'event' => $event,
            'context' => $this->sanitizeContext($context),
            'environment' => [
                'hostname' => gethostname() ?: 'unknown',
                'pid' => getmypid() ?: 0,
            ],
        ];

        $this->buffer[] = $entry;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    /**
     * Flush buffer to storage.
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        if ($this->writer !== null) {
            ($this->writer)($this->buffer);
        } else {
            $this->writeToFile($this->buffer);
        }

        $this->buffer = [];
    }

    /**
     * Get logging configuration for Laravel.
     *
     * @return array<string, mixed>
     */
    public function getLaravelConfig(): array
    {
        return [
            'channels' => [
                'audit' => [
                    'driver' => 'daily',
                    'path' => $this->logPath.'/audit.log',
                    'level' => 'debug',
                    'days' => $this->getRetentionDays(),
                    'permission' => 0644,
                ],
                'security' => [
                    'driver' => 'daily',
                    'path' => $this->logPath.'/security.log',
                    'level' => 'warning',
                    'days' => $this->getRetentionDays() * 2, // Keep security logs longer
                    'permission' => 0600, // More restrictive
                ],
                'api' => [
                    'driver' => 'daily',
                    'path' => $this->logPath.'/api.log',
                    'level' => 'info',
                    'days' => $this->getRetentionDays(),
                ],
            ],
            'stack_channels' => ['audit', 'security', 'api'],
        ];
    }

    /**
     * Get middleware configuration for automatic logging.
     *
     * @return array<string, mixed>
     */
    public static function getMiddlewarePatterns(): array
    {
        return [
            'api_logging' => [
                'description' => 'Middleware to log all API requests',
                'code' => <<<'PHP'
                    public function handle($request, Closure $next)
                    {
                        $start = microtime(true);

                        $response = $next($request);

                        $duration = (microtime(true) - $start) * 1000;

                        app(AuditLogger::class)->logApi(
                            method: $request->method(),
                            endpoint: $request->path(),
                            statusCode: $response->getStatusCode(),
                            duration: $duration,
                            userId: $request->user()?->id,
                            context: [
                                'ip_address' => $request->ip(),
                                'user_agent' => $request->userAgent(),
                                'request_id' => $request->header('X-Request-ID'),
                            ]
                        );

                        return $response;
                    }
                    PHP,
            ],
            'auth_logging' => [
                'description' => 'Event listeners for authentication events',
                'events' => [
                    'Login' => "logAuth('user.login', \$event->user->id)",
                    'Logout' => "logAuth('user.logout', \$event->user->id)",
                    'Failed' => "logAuth('user.login_failed', null, ['email' => \$event->credentials['email'] ?? 'unknown'], 'warning')",
                    'Lockout' => "logSecurity('user.lockout', ['email' => \$event->request->email ?? 'unknown'])",
                ],
            ],
        ];
    }

    /**
     * Get event tracking recommendations.
     *
     * @return array<string, array<string>>
     */
    public static function getRecommendedEvents(): array
    {
        return [
            self::CATEGORY_AUTH => [
                'user.login',
                'user.logout',
                'user.login_failed',
                'user.password_reset',
                'user.password_changed',
                'user.email_verified',
                'user.two_factor_enabled',
                'user.two_factor_disabled',
                'user.session_invalidated',
                'api.token_created',
                'api.token_revoked',
            ],
            self::CATEGORY_AUTHZ => [
                'permission.granted',
                'permission.denied',
                'role.assigned',
                'role.removed',
                'access.admin_area',
                'access.restricted_resource',
            ],
            self::CATEGORY_DATA_ACCESS => [
                'model.created',
                'model.updated',
                'model.deleted',
                'model.restored',
                'model.force_deleted',
                'export.requested',
                'export.completed',
                'import.started',
                'import.completed',
            ],
            self::CATEGORY_SECURITY => [
                'suspicious.multiple_failed_logins',
                'suspicious.ip_change',
                'suspicious.unusual_activity',
                'attack.sql_injection_attempt',
                'attack.xss_attempt',
                'attack.csrf_mismatch',
                'rate_limit.exceeded',
            ],
        ];
    }

    /**
     * Get recommended packages for logging in Laravel projects.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRecommendedPackages(): array
    {
        return [
            'spatie/laravel-activitylog' => [
                'description' => 'Log activity inside your Laravel app with a beautiful UI',
                'package' => 'spatie/laravel-activitylog',
                'version' => '^4.0',
                'use_cases' => [
                    'Model activity tracking (created, updated, deleted)',
                    'Custom event logging',
                    'User action attribution',
                    'Causation tracking (who caused what)',
                    'Built-in cleanup commands',
                ],
                'integration' => <<<'PHP'
                    // In your model
                    use Spatie\Activitylog\Traits\LogsActivity;
                    use Spatie\Activitylog\LogOptions;

                    class User extends Model
                    {
                        use LogsActivity;

                        public function getActivitylogOptions(): LogOptions
                        {
                            return LogOptions::defaults()
                                ->logAll()
                                ->logOnlyDirty();
                        }
                    }
                    PHP,
            ],
            'spatie/laravel-backup' => [
                'description' => 'Backup your application including database and files',
                'package' => 'spatie/laravel-backup',
                'version' => '^8.0',
                'use_cases' => [
                    'Database backups',
                    'File system backups',
                    'Notifications on backup status',
                    'Health checks for backups',
                ],
            ],
            'spatie/laravel-ray' => [
                'description' => 'Debug with Ray - a desktop app for debugging',
                'package' => 'spatie/laravel-ray',
                'version' => '^1.0',
                'use_cases' => [
                    'Real-time debugging',
                    'Query inspection',
                    'Request/response viewing',
                    'Development-only (auto-disabled in production)',
                ],
                'environment' => 'local',
            ],
        ];
    }

    /**
     * Sanitize context to remove sensitive data.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower($key);

            // Check if field is sensitive
            foreach (self::SENSITIVE_FIELDS as $sensitive) {
                if (str_contains($normalizedKey, $sensitive)) {
                    $sanitized[$key] = '[REDACTED]';

                    continue 2;
                }
            }

            // Recursively sanitize arrays
            if (is_array($value)) {
                /** @var array<string, mixed> $nestedArray */
                $nestedArray = $value;
                $sanitized[$key] = $this->sanitizeContext($nestedArray);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * @param  array<array<string, mixed>>  $entries
     */
    private function writeToFile(array $entries): void
    {
        $date = date('Y-m-d');
        $filename = $this->logPath."/audit-{$date}.log";

        // Ensure directory exists
        $dir = dirname($filename);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $lines = [];
        foreach ($entries as $entry) {
            $lines[] = json_encode($entry, JSON_UNESCAPED_SLASHES);
        }

        file_put_contents(
            $filename,
            implode("\n", $lines)."\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private function configureForScale(): void
    {
        $this->bufferSize = match ($this->projectScale) {
            'prototype', 'small' => 50,
            'medium' => 100,
            'large' => 200,
            'massive' => 500,
            default => 100,
        };
    }

    private function getRetentionDays(): int
    {
        return match ($this->projectScale) {
            'prototype' => 7,
            'small' => 30,
            'medium' => 90,
            'large' => 365,
            'massive' => 730,
            default => 30,
        };
    }

    public function __destruct()
    {
        $this->flush();
    }
}
