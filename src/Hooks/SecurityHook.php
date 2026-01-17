<?php

declare(strict_types=1);

namespace LaraForge\Hooks;

use LaraForge\Hooks\Contracts\HookInterface;
use LaraForge\Project\ProjectContext;

/**
 * Security Hook
 *
 * Executes security checks during agent operations.
 * Validates generated code against OWASP Top 10, framework-specific rules,
 * and community security best practices.
 */
final class SecurityHook implements HookInterface
{
    /**
     * Security check categories.
     */
    public const CATEGORY_OWASP = 'owasp';

    public const CATEGORY_FRAMEWORK = 'framework';

    public const CATEGORY_COMMUNITY = 'community';

    public const CATEGORY_CUSTOM = 'custom';

    /**
     * Severity levels.
     */
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_INFO = 'info';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $rules = [];

    /**
     * @var array<string, bool>
     */
    private array $enabledCategories = [
        self::CATEGORY_OWASP => true,
        self::CATEGORY_FRAMEWORK => true,
        self::CATEGORY_COMMUNITY => true,
        self::CATEGORY_CUSTOM => true,
    ];

    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    public function __construct()
    {
        $this->registerDefaultRules();
    }

    public function identifier(): string
    {
        return 'security';
    }

    public function name(): string
    {
        return 'Security Validation Hook';
    }

    public function type(): string
    {
        return 'validation';
    }

    public function priority(): int
    {
        return 100; // High priority - run early
    }

    public function isSkippable(): bool
    {
        return false; // Security checks should not be skipped
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return [
            'description' => 'Validates code against security rules',
            'categories' => array_keys($this->enabledCategories),
            'rules_count' => count($this->rules),
        ];
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @return array{continue: bool, data?: array<string, mixed>, error?: string}
     */
    public function execute(ProjectContext $context, array $eventData = []): array
    {
        $content = $eventData['content'] ?? '';
        $filePath = $eventData['file_path'] ?? '';

        $violations = $this->scan($content, $filePath);

        // Block if critical violations found
        $critical = array_filter($violations, fn ($v) => $v['severity'] === self::SEVERITY_CRITICAL);

        if (! empty($critical)) {
            return [
                'continue' => false,
                'error' => 'Critical security violations detected',
                'data' => [
                    'violations' => $violations,
                    'summary' => $this->generateSummary($violations),
                ],
            ];
        }

        return [
            'continue' => true,
            'data' => [
                'violations' => $violations,
                'summary' => $this->generateSummary($violations),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    public function shouldRun(ProjectContext $context, array $eventData = []): bool
    {
        // Run on code generation and file modification operations
        $operation = $eventData['operation'] ?? '';

        return in_array($operation, [
            'generate',
            'write',
            'edit',
            'create',
            'modify',
        ], true);
    }

    /**
     * Scan content for security violations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scan(string $content, string $filePath = ''): array
    {
        $violations = [];
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        foreach ($this->rules as $ruleId => $rule) {
            // Skip disabled categories
            $category = $rule['category'] ?? self::CATEGORY_CUSTOM;
            if (! ($this->enabledCategories[$category] ?? true)) {
                continue;
            }

            // Check if rule applies to this file type
            $fileTypes = $rule['file_types'] ?? ['php'];
            if ($extension !== '' && ! in_array($extension, $fileTypes, true) && ! in_array('*', $fileTypes, true)) {
                continue;
            }

            // Check pattern
            $pattern = $rule['pattern'] ?? null;
            if ($pattern !== null && preg_match($pattern, $content, $matches)) {
                $violations[] = [
                    'rule_id' => $ruleId,
                    'category' => $category,
                    'severity' => $rule['severity'] ?? self::SEVERITY_MEDIUM,
                    'message' => $rule['message'] ?? 'Security violation detected',
                    'recommendation' => $rule['recommendation'] ?? null,
                    'doc_url' => $rule['doc_url'] ?? null,
                    'match' => $matches[0] ?? null,
                    'file' => $filePath,
                ];
            }
        }

        return $violations;
    }

    /**
     * Register a security rule.
     *
     * @param  array<string, mixed>  $rule
     */
    public function registerRule(string $id, array $rule): void
    {
        $this->rules[$id] = $rule;
    }

    /**
     * Get all registered rules.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Enable/disable a category.
     */
    public function setCategory(string $category, bool $enabled): void
    {
        $this->enabledCategories[$category] = $enabled;
    }

    /**
     * Set context for rule evaluation.
     *
     * @param  array<string, mixed>  $context
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Get rules by category.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRulesByCategory(string $category): array
    {
        return array_filter(
            $this->rules,
            fn ($rule) => ($rule['category'] ?? '') === $category
        );
    }

    /**
     * Get rules by severity.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRulesBySeverity(string $severity): array
    {
        return array_filter(
            $this->rules,
            fn ($rule) => ($rule['severity'] ?? '') === $severity
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $violations
     * @return array<string, mixed>
     */
    private function generateSummary(array $violations): array
    {
        $bySeverity = [];
        foreach ($violations as $v) {
            $severity = $v['severity'] ?? 'unknown';
            $bySeverity[$severity] = ($bySeverity[$severity] ?? 0) + 1;
        }

        return [
            'total' => count($violations),
            'by_severity' => $bySeverity,
            'critical_count' => $bySeverity[self::SEVERITY_CRITICAL] ?? 0,
            'high_count' => $bySeverity[self::SEVERITY_HIGH] ?? 0,
        ];
    }

    private function registerDefaultRules(): void
    {
        // OWASP Top 10 Rules
        $this->registerOwaspRules();

        // Framework-specific Rules
        $this->registerFrameworkRules();

        // Community Best Practices
        $this->registerCommunityRules();
    }

    private function registerOwaspRules(): void
    {
        // A01:2021 - Broken Access Control
        $this->registerRule('owasp-a01-auth-bypass', [
            'category' => self::CATEGORY_OWASP,
            'severity' => self::SEVERITY_CRITICAL,
            'pattern' => '/->withoutMiddleware\s*\(\s*[\'"]auth/i',
            'message' => 'Removing authentication middleware detected',
            'recommendation' => 'Ensure authentication is not bypassed unintentionally',
            'doc_url' => 'https://owasp.org/Top10/A01_2021-Broken_Access_Control/',
            'file_types' => ['php'],
        ]);

        // A02:2021 - Cryptographic Failures
        $this->registerRule('owasp-a02-weak-hash', [
            'category' => self::CATEGORY_OWASP,
            'severity' => self::SEVERITY_CRITICAL,
            'pattern' => '/\b(md5|sha1)\s*\([^)]*(\$password|\$pass|\$pwd)/i',
            'message' => 'Weak hash function used for passwords',
            'recommendation' => 'Use password_hash() with PASSWORD_DEFAULT or PASSWORD_ARGON2ID',
            'doc_url' => 'https://owasp.org/Top10/A02_2021-Cryptographic_Failures/',
            'file_types' => ['php'],
        ]);

        $this->registerRule('owasp-a02-hardcoded-secret', [
            'category' => self::CATEGORY_OWASP,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/[\'"](?:api[_-]?key|secret|password|token)[\'"]\\s*[=:]\\s*[\'"][a-zA-Z0-9]{16,}/i',
            'message' => 'Possible hardcoded secret detected',
            'recommendation' => 'Use environment variables for secrets',
            'doc_url' => 'https://owasp.org/Top10/A02_2021-Cryptographic_Failures/',
            'file_types' => ['php', 'js', 'ts', 'json', 'yaml', 'yml'],
        ]);

        // A03:2021 - Injection
        $this->registerRule('owasp-a03-sql-injection', [
            'category' => self::CATEGORY_OWASP,
            'severity' => self::SEVERITY_CRITICAL,
            'pattern' => '/DB::raw\s*\([^)]*\$(?!trusted|safe)/i',
            'message' => 'Potential SQL injection with DB::raw()',
            'recommendation' => 'Use parameter binding instead of interpolating variables',
            'doc_url' => 'https://owasp.org/Top10/A03_2021-Injection/',
            'file_types' => ['php'],
        ]);

        $this->registerRule('owasp-a03-command-injection', [
            'category' => self::CATEGORY_OWASP,
            'severity' => self::SEVERITY_CRITICAL,
            'pattern' => '/\b(exec|shell_exec|system|passthru|popen|proc_open)\s*\([^)]*\$/i',
            'message' => 'Potential command injection',
            'recommendation' => 'Use escapeshellarg() and escapeshellcmd() for user input',
            'doc_url' => 'https://owasp.org/Top10/A03_2021-Injection/',
            'file_types' => ['php'],
        ]);

        // A05:2021 - Security Misconfiguration
        $this->registerRule('owasp-a05-debug-mode', [
            'category' => self::CATEGORY_OWASP,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/APP_DEBUG\s*=\s*true/i',
            'message' => 'Debug mode enabled',
            'recommendation' => 'Ensure APP_DEBUG=false in production',
            'doc_url' => 'https://owasp.org/Top10/A05_2021-Security_Misconfiguration/',
            'file_types' => ['env', 'php'],
        ]);

        // A07:2021 - Cross-Site Scripting (XSS)
        $this->registerRule('owasp-a07-xss-unescaped', [
            'category' => self::CATEGORY_OWASP,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/\{!!\s*\$(?!trusted|safe|html|content)/i',
            'message' => 'Unescaped output in Blade template',
            'recommendation' => 'Use {{ }} for escaped output, only use {!! !!} for trusted HTML',
            'doc_url' => 'https://owasp.org/Top10/A07_2021-Cross-Site_Scripting/',
            'file_types' => ['php', 'blade.php'],
        ]);

        $this->registerRule('owasp-a07-xss-innerhtml', [
            'category' => self::CATEGORY_OWASP,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/v-html\s*=|dangerouslySetInnerHTML/i',
            'message' => 'Raw HTML rendering detected',
            'recommendation' => 'Sanitize HTML content before rendering',
            'doc_url' => 'https://owasp.org/Top10/A07_2021-Cross-Site_Scripting/',
            'file_types' => ['vue', 'jsx', 'tsx', 'js', 'ts'],
        ]);
    }

    private function registerFrameworkRules(): void
    {
        // Laravel Mass Assignment
        $this->registerRule('laravel-mass-assignment', [
            'category' => self::CATEGORY_FRAMEWORK,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/protected\s+\$guarded\s*=\s*\[\s*\]/',
            'message' => 'Empty $guarded allows all attributes for mass assignment',
            'recommendation' => 'Define $fillable with allowed attributes instead',
            'doc_url' => 'https://laravel.com/docs/11.x/eloquent#mass-assignment',
            'file_types' => ['php'],
        ]);

        // Laravel CSRF
        $this->registerRule('laravel-csrf-missing', [
            'category' => self::CATEGORY_FRAMEWORK,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/<form[^>]*method\s*=\s*["\']post["\'][^>]*>(?![\s\S]*@csrf)/is',
            'message' => 'Form without @csrf directive',
            'recommendation' => 'Add @csrf directive to all POST forms',
            'doc_url' => 'https://laravel.com/docs/11.x/csrf',
            'file_types' => ['php', 'blade.php'],
        ]);

        // Laravel Validation
        $this->registerRule('laravel-no-validation', [
            'category' => self::CATEGORY_FRAMEWORK,
            'severity' => self::SEVERITY_MEDIUM,
            'pattern' => '/\$request->all\(\)\s*(?![^;]*validate)/i',
            'message' => 'Using $request->all() without validation',
            'recommendation' => 'Validate input before using or use $request->validated()',
            'doc_url' => 'https://laravel.com/docs/11.x/validation',
            'file_types' => ['php'],
        ]);

        // Livewire wire:model
        $this->registerRule('livewire-wire-model-security', [
            'category' => self::CATEGORY_FRAMEWORK,
            'severity' => self::SEVERITY_MEDIUM,
            'pattern' => '/wire:model(?:\.live)?\s*=\s*["\'](?![\w.]+["\']\s)/i',
            'message' => 'Complex wire:model binding detected',
            'recommendation' => 'Ensure wire:model properties are validated in component',
            'doc_url' => 'https://livewire.laravel.com/docs/properties#security-concerns',
            'file_types' => ['php', 'blade.php'],
        ]);

        // Vue/React state exposure
        $this->registerRule('frontend-sensitive-state', [
            'category' => self::CATEGORY_FRAMEWORK,
            'severity' => self::SEVERITY_MEDIUM,
            'pattern' => '/(password|secret|token|apiKey)\s*[=:]\s*(ref|reactive|useState)/i',
            'message' => 'Sensitive data in frontend state',
            'recommendation' => 'Never store secrets in frontend state',
            'doc_url' => 'https://cheatsheetseries.owasp.org/cheatsheets/HTML5_Security_Cheat_Sheet.html',
            'file_types' => ['vue', 'jsx', 'tsx', 'js', 'ts'],
        ]);
    }

    private function registerCommunityRules(): void
    {
        // Eval usage
        $this->registerRule('community-no-eval', [
            'category' => self::CATEGORY_COMMUNITY,
            'severity' => self::SEVERITY_CRITICAL,
            'pattern' => '/\beval\s*\(/i',
            'message' => 'eval() usage detected',
            'recommendation' => 'Avoid eval() as it can execute arbitrary code',
            'file_types' => ['php', 'js', 'ts'],
        ]);

        // File inclusion
        $this->registerRule('community-dynamic-include', [
            'category' => self::CATEGORY_COMMUNITY,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/\b(include|require)(_once)?\s*\([^)]*\$/i',
            'message' => 'Dynamic file inclusion',
            'recommendation' => 'Use a whitelist for included files',
            'file_types' => ['php'],
        ]);

        // Unserialize
        $this->registerRule('community-unserialize', [
            'category' => self::CATEGORY_COMMUNITY,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/\bunserialize\s*\([^)]*\$/i',
            'message' => 'unserialize() with user input',
            'recommendation' => 'Use JSON for serialization or specify allowed_classes',
            'file_types' => ['php'],
        ]);

        // Exposed .env
        $this->registerRule('community-env-exposure', [
            'category' => self::CATEGORY_COMMUNITY,
            'severity' => self::SEVERITY_HIGH,
            'pattern' => '/env\s*\(\s*[\'"](?:DB_PASSWORD|APP_KEY|AWS_SECRET)/i',
            'message' => 'Sensitive env() call outside config',
            'recommendation' => 'Use env() only in config files, use config() elsewhere',
            'doc_url' => 'https://laravel.com/docs/11.x/configuration#configuration-caching',
            'file_types' => ['php'],
        ]);

        // Console output in production
        $this->registerRule('community-console-log', [
            'category' => self::CATEGORY_COMMUNITY,
            'severity' => self::SEVERITY_LOW,
            'pattern' => '/console\.(log|debug|info|warn)\s*\(/i',
            'message' => 'Console output detected',
            'recommendation' => 'Remove console statements before production',
            'file_types' => ['js', 'ts', 'vue', 'jsx', 'tsx'],
        ]);

        // Disabled HTTPS
        $this->registerRule('community-insecure-url', [
            'category' => self::CATEGORY_COMMUNITY,
            'severity' => self::SEVERITY_MEDIUM,
            'pattern' => '/[\'"]http:\/\/(?!localhost|127\.0\.0\.1)/i',
            'message' => 'Insecure HTTP URL detected',
            'recommendation' => 'Use HTTPS for external URLs',
            'file_types' => ['php', 'js', 'ts', 'json', 'yaml', 'yml'],
        ]);

        // SQL in strings
        $this->registerRule('community-raw-sql', [
            'category' => self::CATEGORY_COMMUNITY,
            'severity' => self::SEVERITY_MEDIUM,
            'pattern' => '/[\'"]SELECT\s+.*\s+FROM\s+.*WHERE\s+.*\$[a-z]/i',
            'message' => 'Raw SQL query with variable interpolation',
            'recommendation' => 'Use query builder or Eloquent with parameter binding',
            'file_types' => ['php'],
        ]);
    }

    /**
     * Create instance.
     */
    public static function create(): self
    {
        return new self;
    }
}
