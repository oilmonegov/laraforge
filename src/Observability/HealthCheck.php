<?php

declare(strict_types=1);

namespace LaraForge\Observability;

use Exception;
use PDO;

/**
 * Health Check System
 *
 * Provides health check capabilities for the application.
 * Supports both simple and comprehensive health checks.
 */
final class HealthCheck
{
    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_UNHEALTHY = 'unhealthy';

    /**
     * @var array<string, array{check: callable(): array{status: string, message: string, data?: array<string, mixed>}, critical: bool}>
     */
    private array $checks = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $results = [];

    public function __construct(
        private readonly string $appName = 'app',
        private readonly string $version = '1.0.0',
    ) {
        $this->registerDefaultChecks();
    }

    /**
     * Register a health check.
     *
     * @param  callable(): array{status: string, message: string, data?: array<string, mixed>}  $check
     */
    public function registerCheck(string $name, callable $check, bool $critical = false): self
    {
        $this->checks[$name] = [
            'check' => $check,
            'critical' => $critical,
        ];

        return $this;
    }

    /**
     * Run all health checks.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $this->results = [];
        $overallStatus = self::STATUS_HEALTHY;
        $hasUnhealthyCritical = false;

        foreach ($this->checks as $name => $config) {
            try {
                $result = ($config['check'])();
                $this->results[$name] = $result;

                if ($result['status'] === self::STATUS_UNHEALTHY) {
                    if ($config['critical']) {
                        $hasUnhealthyCritical = true;
                    }
                    $overallStatus = self::STATUS_UNHEALTHY;
                } elseif ($result['status'] === self::STATUS_DEGRADED && $overallStatus !== self::STATUS_UNHEALTHY) {
                    $overallStatus = self::STATUS_DEGRADED;
                }
            } catch (Exception $e) {
                $this->results[$name] = [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => 'Check failed: '.$e->getMessage(),
                ];
                if ($config['critical']) {
                    $hasUnhealthyCritical = true;
                }
                $overallStatus = self::STATUS_UNHEALTHY;
            }
        }

        return [
            'status' => $overallStatus,
            'timestamp' => date('c'),
            'app' => $this->appName,
            'version' => $this->version,
            'checks' => $this->results,
            'critical_healthy' => ! $hasUnhealthyCritical,
        ];
    }

    /**
     * Run liveness check (is the app running?).
     *
     * @return array{status: string, timestamp: string}
     */
    public function liveness(): array
    {
        return [
            'status' => self::STATUS_HEALTHY,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Run readiness check (is the app ready to serve traffic?).
     *
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $criticalChecks = [];
        $status = self::STATUS_HEALTHY;

        foreach ($this->checks as $name => $config) {
            if (! $config['critical']) {
                continue;
            }

            try {
                $result = ($config['check'])();
                $criticalChecks[$name] = $result['status'];

                if ($result['status'] === self::STATUS_UNHEALTHY) {
                    $status = self::STATUS_UNHEALTHY;
                }
            } catch (Exception $e) {
                $criticalChecks[$name] = self::STATUS_UNHEALTHY;
                $status = self::STATUS_UNHEALTHY;
            }
        }

        return [
            'status' => $status,
            'timestamp' => date('c'),
            'checks' => $criticalChecks,
        ];
    }

    /**
     * Get HTTP status code for health check result.
     */
    public function getHttpStatus(string $status): int
    {
        return match ($status) {
            self::STATUS_HEALTHY => 200,
            self::STATUS_DEGRADED => 200, // Still serving traffic
            self::STATUS_UNHEALTHY => 503,
            default => 500,
        };
    }

    /**
     * Create database check.
     *
     * @return callable(): array{status: string, message: string, data?: array<string, mixed>}
     */
    public static function createDatabaseCheck(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
    ): callable {
        return function () use ($dsn, $username, $password): array {
            try {
                $start = microtime(true);
                $pdo = new PDO($dsn, $username, $password);
                $pdo->query('SELECT 1');
                $duration = (microtime(true) - $start) * 1000;

                return [
                    'status' => self::STATUS_HEALTHY,
                    'message' => 'Database connection successful',
                    'data' => [
                        'response_time_ms' => round($duration, 2),
                    ],
                ];
            } catch (Exception $e) {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => 'Database connection failed: '.$e->getMessage(),
                ];
            }
        };
    }

    /**
     * Create Redis check.
     *
     * @return callable(): array{status: string, message: string, data?: array<string, mixed>}
     */
    public static function createRedisCheck(string $host = '127.0.0.1', int $port = 6379): callable
    {
        return function () use ($host, $port): array {
            try {
                $socket = @fsockopen($host, $port, $errno, $errstr, 1);

                if ($socket === false) {
                    return [
                        'status' => self::STATUS_UNHEALTHY,
                        'message' => "Redis connection failed: {$errstr}",
                    ];
                }

                fclose($socket);

                return [
                    'status' => self::STATUS_HEALTHY,
                    'message' => 'Redis connection successful',
                ];
            } catch (Exception $e) {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => 'Redis check failed: '.$e->getMessage(),
                ];
            }
        };
    }

    /**
     * Create disk space check.
     *
     * @return callable(): array{status: string, message: string, data?: array<string, mixed>}
     */
    public static function createDiskSpaceCheck(
        string $path = '/',
        int $warnThresholdPercent = 80,
        int $criticalThresholdPercent = 90,
    ): callable {
        return function () use ($path, $warnThresholdPercent, $criticalThresholdPercent): array {
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);

            if ($total === false || $free === false) {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => 'Could not determine disk space',
                ];
            }

            $usedPercent = (($total - $free) / $total) * 100;

            $status = self::STATUS_HEALTHY;
            if ($usedPercent >= $criticalThresholdPercent) {
                $status = self::STATUS_UNHEALTHY;
            } elseif ($usedPercent >= $warnThresholdPercent) {
                $status = self::STATUS_DEGRADED;
            }

            return [
                'status' => $status,
                'message' => sprintf('Disk usage: %.1f%%', $usedPercent),
                'data' => [
                    'path' => $path,
                    'total_bytes' => $total,
                    'free_bytes' => $free,
                    'used_percent' => round($usedPercent, 1),
                ],
            ];
        };
    }

    /**
     * Create memory check.
     *
     * @return callable(): array{status: string, message: string, data?: array<string, mixed>}
     */
    public static function createMemoryCheck(int $warnThresholdMb = 256): callable
    {
        return function () use ($warnThresholdMb): array {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = self::getMemoryLimitBytes();
            $usedMb = $memoryUsage / 1024 / 1024;

            $status = self::STATUS_HEALTHY;
            $message = sprintf('Memory usage: %.1fMB', $usedMb);

            if ($memoryLimit > 0) {
                $usedPercent = ($memoryUsage / $memoryLimit) * 100;
                $message .= sprintf(' (%.1f%% of limit)', $usedPercent);

                if ($usedPercent > 90) {
                    $status = self::STATUS_UNHEALTHY;
                } elseif ($usedPercent > 75 || $usedMb > $warnThresholdMb) {
                    $status = self::STATUS_DEGRADED;
                }
            } elseif ($usedMb > $warnThresholdMb) {
                $status = self::STATUS_DEGRADED;
            }

            return [
                'status' => $status,
                'message' => $message,
                'data' => [
                    'used_bytes' => $memoryUsage,
                    'peak_bytes' => memory_get_peak_usage(true),
                    'limit_bytes' => $memoryLimit,
                ],
            ];
        };
    }

    /**
     * Create external service check.
     *
     * @return callable(): array{status: string, message: string, data?: array<string, mixed>}
     */
    public static function createExternalServiceCheck(
        string $url,
        int $timeoutSeconds = 5,
        int $expectedStatus = 200,
    ): callable {
        return function () use ($url, $timeoutSeconds, $expectedStatus): array {
            $start = microtime(true);

            $ch = curl_init($url);
            if ($ch === false) {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => 'Could not initialize curl',
                ];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
                CURLOPT_NOBODY => true,
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $duration = (microtime(true) - $start) * 1000;

            if ($error !== '') {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => "Service unreachable: {$error}",
                    'data' => ['url' => $url],
                ];
            }

            if ($httpCode !== $expectedStatus) {
                return [
                    'status' => self::STATUS_UNHEALTHY,
                    'message' => "Unexpected status code: {$httpCode}",
                    'data' => [
                        'url' => $url,
                        'status_code' => $httpCode,
                        'expected' => $expectedStatus,
                    ],
                ];
            }

            return [
                'status' => self::STATUS_HEALTHY,
                'message' => 'Service healthy',
                'data' => [
                    'url' => $url,
                    'response_time_ms' => round($duration, 2),
                ],
            ];
        };
    }

    /**
     * Register default health checks.
     */
    private function registerDefaultChecks(): void
    {
        // Memory check
        $this->registerCheck('memory', self::createMemoryCheck(), critical: false);

        // Disk space check
        $this->registerCheck('disk', self::createDiskSpaceCheck(), critical: false);
    }

    /**
     * Get PHP memory limit in bytes.
     */
    private static function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === '' || $limit === '-1') {
            return 0;
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
