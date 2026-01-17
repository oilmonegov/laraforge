<?php

declare(strict_types=1);

namespace LaraForge\Config;

use DateTimeImmutable;

/**
 * Configuration Protection
 *
 * Protects critical configuration and architecture files from casual modification.
 * Requires explicit permission, separate PRs, and detailed justification for changes.
 * Team-friendly: checks existing files before overwriting.
 */
final class ConfigProtection
{
    /**
     * Protection levels.
     */
    public const LEVEL_NONE = 'none';                    // No protection

    public const LEVEL_WARN = 'warn';                    // Warn but allow

    public const LEVEL_CONFIRM = 'confirm';              // Require explicit confirmation

    public const LEVEL_PROTECTED = 'protected';          // Require justification

    public const LEVEL_CRITICAL = 'critical';            // Require separate PR/commit

    /**
     * File categories and their default protection levels.
     *
     * @var array<string, array<string, mixed>>
     */
    private const PROTECTED_PATTERNS = [
        // Architecture tests - highest protection
        'architecture_tests' => [
            'patterns' => ['tests/Architecture/**', '**/ArchTest.php', '**/Arch*.php'],
            'level' => self::LEVEL_CRITICAL,
            'requires_separate_pr' => true,
            'description' => 'Architecture tests define project structure rules',
        ],
        // Core configuration
        'app_config' => [
            'patterns' => ['config/app.php', 'config/auth.php', 'config/database.php'],
            'level' => self::LEVEL_PROTECTED,
            'requires_separate_pr' => false,
            'description' => 'Core application configuration',
        ],
        // Security configuration
        'security_config' => [
            'patterns' => ['config/cors.php', 'config/sanctum.php', 'config/hashing.php'],
            'level' => self::LEVEL_PROTECTED,
            'requires_separate_pr' => true,
            'description' => 'Security-sensitive configuration',
        ],
        // Service providers
        'service_providers' => [
            'patterns' => ['app/Providers/**ServiceProvider.php', 'bootstrap/providers.php'],
            'level' => self::LEVEL_CONFIRM,
            'requires_separate_pr' => false,
            'description' => 'Service provider registration affects entire app',
        ],
        // Environment files
        'environment' => [
            'patterns' => ['.env', '.env.*', 'env.example'],
            'level' => self::LEVEL_WARN,
            'requires_separate_pr' => false,
            'description' => 'Environment configuration',
        ],
        // CI/CD configuration
        'ci_cd' => [
            'patterns' => ['.github/workflows/**', '.gitlab-ci.yml', 'Dockerfile', 'docker-compose.yml'],
            'level' => self::LEVEL_PROTECTED,
            'requires_separate_pr' => true,
            'description' => 'CI/CD and deployment configuration',
        ],
        // LaraForge configuration
        'laraforge' => [
            'patterns' => ['laraforge.php', '.laraforge/**'],
            'level' => self::LEVEL_CONFIRM,
            'requires_separate_pr' => false,
            'description' => 'LaraForge project configuration',
        ],
        // PHPStan configuration
        'static_analysis' => [
            'patterns' => ['phpstan.neon', 'phpstan.neon.dist', 'phpstan-baseline.neon'],
            'level' => self::LEVEL_PROTECTED,
            'requires_separate_pr' => true,
            'description' => 'Static analysis rules affect code quality standards',
        ],
        // Composer configuration
        'composer' => [
            'patterns' => ['composer.json', 'composer.lock'],
            'level' => self::LEVEL_CONFIRM,
            'requires_separate_pr' => false,
            'description' => 'Package dependencies',
        ],
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $customProtections = [];

    /**
     * @var array<array<string, mixed>>
     */
    private array $changeLog = [];

    public function __construct(
        private readonly string $projectPath,
        private readonly ?string $logPath = null,
    ) {
        $this->loadCustomProtections();
    }

    /**
     * Check if a file is protected.
     *
     * @return array{protected: bool, level: string, category: string|null, requires_separate_pr: bool, description: string|null}
     */
    public function checkProtection(string $filePath): array
    {
        $relativePath = $this->getRelativePath($filePath);
        $allProtections = array_merge(self::PROTECTED_PATTERNS, $this->customProtections);

        foreach ($allProtections as $category => $config) {
            /** @var array<string> $patterns */
            $patterns = $config['patterns'] ?? [];
            foreach ($patterns as $pattern) {
                if ($this->matchesPattern($relativePath, $pattern)) {
                    /** @var string $level */
                    $level = $config['level'] ?? self::LEVEL_NONE;
                    /** @var bool $requiresSeparatePr */
                    $requiresSeparatePr = $config['requires_separate_pr'] ?? false;
                    /** @var string|null $description */
                    $description = $config['description'] ?? null;

                    return [
                        'protected' => true,
                        'level' => $level,
                        'category' => $category,
                        'requires_separate_pr' => $requiresSeparatePr,
                        'description' => $description,
                    ];
                }
            }
        }

        return [
            'protected' => false,
            'level' => self::LEVEL_NONE,
            'category' => null,
            'requires_separate_pr' => false,
            'description' => null,
        ];
    }

    /**
     * Check if file modification is allowed.
     *
     * @param  array<string, mixed>  $context
     * @return array{allowed: bool, reason: string|null, requires_action: string|null}
     */
    public function canModify(string $filePath, array $context = []): array
    {
        $protection = $this->checkProtection($filePath);

        if (! $protection['protected']) {
            return ['allowed' => true, 'reason' => null, 'requires_action' => null];
        }

        // Check if explicit permission was granted
        if (isset($context['explicit_permission']) && $context['explicit_permission'] === true) {
            $this->logChange($filePath, 'modify', $protection, $context);

            return ['allowed' => true, 'reason' => 'Explicit permission granted', 'requires_action' => null];
        }

        return match ($protection['level']) {
            self::LEVEL_WARN => [
                'allowed' => true,
                'reason' => "Warning: {$protection['description']}",
                'requires_action' => 'acknowledge',
            ],
            self::LEVEL_CONFIRM => [
                'allowed' => false,
                'reason' => "Confirmation required: {$protection['description']}",
                'requires_action' => 'confirm',
            ],
            self::LEVEL_PROTECTED => [
                'allowed' => false,
                'reason' => "Protected file: {$protection['description']}. Justification required.",
                'requires_action' => 'justify',
            ],
            self::LEVEL_CRITICAL => [
                'allowed' => false,
                'reason' => "Critical file: {$protection['description']}. Requires separate PR/commit with detailed justification.",
                'requires_action' => 'separate_pr',
            ],
            default => ['allowed' => true, 'reason' => null, 'requires_action' => null],
        };
    }

    /**
     * Check if file exists and warn before overwriting.
     *
     * @return array{exists: bool, modified: bool, message: string|null}
     */
    public function checkExisting(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return ['exists' => false, 'modified' => false, 'message' => null];
        }

        // Check if file has been modified from template
        $isModified = $this->hasBeenModified($filePath);

        if ($isModified) {
            return [
                'exists' => true,
                'modified' => true,
                'message' => 'This file exists and appears to have been customized. Overwriting will lose these changes.',
            ];
        }

        return [
            'exists' => true,
            'modified' => false,
            'message' => 'This file exists but appears to be unchanged from the template.',
        ];
    }

    /**
     * Request permission to modify a protected file.
     *
     * @return array{granted: bool, token: string|null, expires_at: string|null}
     */
    public function requestPermission(string $filePath, string $justification, string $requestedBy): array
    {
        $protection = $this->checkProtection($filePath);

        if (! $protection['protected']) {
            return ['granted' => true, 'token' => null, 'expires_at' => null];
        }

        if (strlen($justification) < 20) {
            return ['granted' => false, 'token' => null, 'expires_at' => null];
        }

        // Generate permission token
        $token = bin2hex(random_bytes(16));
        $expiresAt = (new DateTimeImmutable)->modify('+1 hour')->format('c');

        // Store permission
        $this->storePermission($filePath, $token, $justification, $requestedBy, $expiresAt);

        return [
            'granted' => true,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Validate a permission token.
     */
    public function validatePermission(string $filePath, string $token): bool
    {
        $permissions = $this->loadPermissions();
        $key = $this->getPermissionKey($filePath);

        if (! isset($permissions[$key])) {
            return false;
        }

        $permission = $permissions[$key];

        /** @var string $storedToken */
        $storedToken = $permission['token'] ?? '';
        if ($storedToken !== $token) {
            return false;
        }

        /** @var string $expiresAt */
        $expiresAt = $permission['expires_at'] ?? '';
        if (strtotime($expiresAt) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Add custom protection rule.
     *
     * @param  array<string>  $patterns
     */
    public function addProtection(
        string $category,
        array $patterns,
        string $level = self::LEVEL_CONFIRM,
        bool $requiresSeparatePr = false,
        string $description = '',
    ): void {
        $this->customProtections[$category] = [
            'patterns' => $patterns,
            'level' => $level,
            'requires_separate_pr' => $requiresSeparatePr,
            'description' => $description,
        ];

        $this->saveCustomProtections();
    }

    /**
     * Get all protection rules.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllProtections(): array
    {
        return array_merge(self::PROTECTED_PATTERNS, $this->customProtections);
    }

    /**
     * Get change log for auditing.
     *
     * @return array<array<string, mixed>>
     */
    public function getChangeLog(): array
    {
        return $this->changeLog;
    }

    /**
     * Get Git hook script for protection enforcement.
     */
    public function getGitHookScript(): string
    {
        return <<<'BASH'
            #!/bin/bash
            # LaraForge Configuration Protection Hook

            # Get list of staged files
            STAGED_FILES=$(git diff --cached --name-only)

            # Protected file patterns that require separate commits
            CRITICAL_PATTERNS=(
                "tests/Architecture/"
                "phpstan.neon"
                "config/app.php"
                "config/auth.php"
                ".github/workflows/"
            )

            # Check for critical file changes
            CRITICAL_CHANGES=()
            for file in $STAGED_FILES; do
                for pattern in "${CRITICAL_PATTERNS[@]}"; do
                    if [[ "$file" == *"$pattern"* ]]; then
                        CRITICAL_CHANGES+=("$file")
                    fi
                done
            done

            # If critical files changed alongside other files, warn
            if [ ${#CRITICAL_CHANGES[@]} -gt 0 ]; then
                OTHER_FILES=$(echo "$STAGED_FILES" | grep -v -E "(Architecture|phpstan|config/app|config/auth|workflows)")

                if [ -n "$OTHER_FILES" ]; then
                    echo ""
                    echo "⚠️  WARNING: Critical files changed alongside other files"
                    echo ""
                    echo "Critical files:"
                    for file in "${CRITICAL_CHANGES[@]}"; do
                        echo "  - $file"
                    done
                    echo ""
                    echo "These files should typically be in their own commit with detailed justification."
                    echo ""
                    echo "To proceed anyway, use: git commit --no-verify"
                    echo "To commit separately, unstage other files first."
                    echo ""
                    exit 1
                fi
            fi

            exit 0
            BASH;
    }

    /**
     * Get protection status for display.
     *
     * @return array<string, mixed>
     */
    public function getProtectionStatus(): array
    {
        $allProtections = $this->getAllProtections();
        $status = [];

        foreach ($allProtections as $category => $config) {
            $status[$category] = [
                'level' => $config['level'],
                'requires_separate_pr' => $config['requires_separate_pr'] ?? false,
                'description' => $config['description'] ?? '',
                'patterns' => $config['patterns'],
            ];
        }

        return $status;
    }

    private function getRelativePath(string $filePath): string
    {
        if (str_starts_with($filePath, $this->projectPath)) {
            return ltrim(substr($filePath, strlen($this->projectPath)), '/');
        }

        return $filePath;
    }

    private function matchesPattern(string $path, string $pattern): bool
    {
        // Convert glob pattern to regex
        $regex = str_replace(
            ['**', '*', '?'],
            ['.*', '[^/]*', '.'],
            $pattern
        );

        return (bool) preg_match('#^'.$regex.'$#', $path);
    }

    private function hasBeenModified(string $filePath): bool
    {
        // Check for modification markers
        $content = file_get_contents($filePath);

        if ($content === false) {
            return false;
        }

        // Check for common modification indicators
        $indicators = [
            'Modified by',
            'Customized',
            'Custom configuration',
            'DO NOT OVERWRITE',
            '@modified',
        ];

        foreach ($indicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $protection
     * @param  array<string, mixed>  $context
     */
    private function logChange(string $filePath, string $action, array $protection, array $context): void
    {
        $entry = [
            'timestamp' => (new DateTimeImmutable)->format('c'),
            'file' => $this->getRelativePath($filePath),
            'action' => $action,
            'protection_level' => $protection['level'],
            'category' => $protection['category'],
            'justification' => $context['justification'] ?? null,
            'requested_by' => $context['requested_by'] ?? null,
        ];

        $this->changeLog[] = $entry;

        // Write to log file if configured
        if ($this->logPath !== null) {
            $logFile = $this->logPath.'/config-changes.log';
            file_put_contents(
                $logFile,
                json_encode($entry)."\n",
                FILE_APPEND | LOCK_EX
            );
        }
    }

    private function storePermission(
        string $filePath,
        string $token,
        string $justification,
        string $requestedBy,
        string $expiresAt,
    ): void {
        $permissions = $this->loadPermissions();
        $key = $this->getPermissionKey($filePath);

        $permissions[$key] = [
            'file' => $filePath,
            'token' => $token,
            'justification' => $justification,
            'requested_by' => $requestedBy,
            'granted_at' => (new DateTimeImmutable)->format('c'),
            'expires_at' => $expiresAt,
        ];

        $this->savePermissions($permissions);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadPermissions(): array
    {
        $file = $this->projectPath.'/.laraforge/permissions.json';

        if (! file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);

        /** @var array<string, array<string, mixed>> */
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $permissions
     */
    private function savePermissions(array $permissions): void
    {
        $dir = $this->projectPath.'/.laraforge';

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir.'/permissions.json',
            json_encode($permissions, JSON_PRETTY_PRINT)
        );
    }

    private function getPermissionKey(string $filePath): string
    {
        return md5($this->getRelativePath($filePath));
    }

    private function loadCustomProtections(): void
    {
        $file = $this->projectPath.'/.laraforge/protections.json';

        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                /** @var array<string, array<string, mixed>> $protections */
                $protections = is_array($decoded) ? $decoded : [];
                $this->customProtections = $protections;
            }
        }
    }

    private function saveCustomProtections(): void
    {
        $dir = $this->projectPath.'/.laraforge';

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir.'/protections.json',
            json_encode($this->customProtections, JSON_PRETTY_PRINT)
        );
    }
}
