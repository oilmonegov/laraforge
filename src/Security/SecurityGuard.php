<?php

declare(strict_types=1);

namespace LaraForge\Security;

/**
 * Security Guard for Code Generation
 *
 * Ensures all generated code follows security best practices.
 * Validates against OWASP Top 10 and Laravel security guidelines.
 */
final class SecurityGuard
{
    /**
     * @var array<string, SecurityRule>
     */
    private array $rules = [];

    /**
     * @var array<string, string>
     */
    private array $violations = [];

    public function __construct()
    {
        $this->registerDefaultRules();
    }

    /**
     * Validate generated code against security rules.
     *
     * @return array<string, string> Violations found
     */
    public function validate(string $code, string $context = 'general'): array
    {
        $this->violations = [];

        foreach ($this->rules as $rule) {
            if ($rule->appliesTo($context) && $rule->isViolated($code)) {
                $this->violations[$rule->id()] = $rule->message();
            }
        }

        return $this->violations;
    }

    /**
     * Check if code passes all security rules.
     */
    public function passes(string $code, string $context = 'general'): bool
    {
        return count($this->validate($code, $context)) === 0;
    }

    /**
     * Register a custom security rule.
     */
    public function registerRule(SecurityRule $rule): void
    {
        $this->rules[$rule->id()] = $rule;
    }

    /**
     * Get all registered rules.
     *
     * @return array<string, SecurityRule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Get security recommendations for a given code type.
     *
     * @return array<string, string>
     */
    public function getRecommendations(string $codeType): array
    {
        return match ($codeType) {
            'controller' => $this->getControllerRecommendations(),
            'model' => $this->getModelRecommendations(),
            'request' => $this->getRequestRecommendations(),
            'migration' => $this->getMigrationRecommendations(),
            'api' => $this->getApiRecommendations(),
            'authentication' => $this->getAuthRecommendations(),
            'query' => $this->getQueryRecommendations(),
            default => $this->getGeneralRecommendations(),
        };
    }

    private function registerDefaultRules(): void
    {
        // SQL Injection Prevention
        $this->registerRule(new SecurityRule(
            id: 'sql-injection-raw',
            pattern: '/DB::raw\s*\(\s*["\'].*\$(?!_)/i',
            message: 'Potential SQL injection: Avoid using variables directly in DB::raw(). Use parameter binding.',
            contexts: ['controller', 'model', 'query', 'general'],
            severity: 'critical',
        ));

        $this->registerRule(new SecurityRule(
            id: 'sql-injection-whereraw',
            pattern: '/whereRaw\s*\(\s*["\'].*\$(?!_)/i',
            message: 'Potential SQL injection: Avoid interpolating variables in whereRaw(). Use parameter binding.',
            contexts: ['controller', 'model', 'query', 'general'],
            severity: 'critical',
        ));

        // XSS Prevention
        $this->registerRule(new SecurityRule(
            id: 'xss-unescaped-blade',
            pattern: '/\{!!\s*\$(?!trusted|safe|html)/i',
            message: 'Potential XSS: Unescaped output {!! $var !!} should only be used for trusted HTML content.',
            contexts: ['view', 'blade', 'general'],
            severity: 'high',
        ));

        $this->registerRule(new SecurityRule(
            id: 'xss-echo-direct',
            pattern: '/echo\s+\$_(?:GET|POST|REQUEST|COOKIE)/i',
            message: 'Critical XSS: Never directly echo superglobals. Always sanitize user input.',
            contexts: ['controller', 'view', 'general'],
            severity: 'critical',
        ));

        // Command Injection
        $this->registerRule(new SecurityRule(
            id: 'command-injection-exec',
            pattern: '/(?:exec|shell_exec|system|passthru|popen|proc_open)\s*\([^)]*\$/i',
            message: 'Potential command injection: Avoid using variables in shell commands. Use escapeshellarg().',
            contexts: ['controller', 'command', 'general'],
            severity: 'critical',
        ));

        // Insecure Deserialization
        $this->registerRule(new SecurityRule(
            id: 'insecure-unserialize',
            pattern: '/unserialize\s*\(\s*\$(?!_)/i',
            message: 'Insecure deserialization: Avoid unserialize() on user input. Use JSON instead.',
            contexts: ['controller', 'model', 'general'],
            severity: 'critical',
        ));

        // Hardcoded Credentials
        $this->registerRule(new SecurityRule(
            id: 'hardcoded-password',
            pattern: '/["\'](?:password|secret|api[_-]?key|token)\s*["\']?\s*(?:=>|=)\s*["\'][a-zA-Z0-9]{8,}/i',
            message: 'Hardcoded credentials detected. Use environment variables for sensitive data.',
            contexts: ['config', 'controller', 'general'],
            severity: 'critical',
        ));

        // Mass Assignment
        $this->registerRule(new SecurityRule(
            id: 'mass-assignment-guarded-empty',
            pattern: '/protected\s+\$guarded\s*=\s*\[\s*\]/i',
            message: 'Mass assignment vulnerability: Empty $guarded array allows all attributes to be mass-assigned.',
            contexts: ['model', 'general'],
            severity: 'high',
        ));

        // CSRF Protection
        $this->registerRule(new SecurityRule(
            id: 'csrf-missing-token',
            pattern: '/<form[^>]*method\s*=\s*["\'](?:post|put|patch|delete)["\'][^>]*>(?!.*@csrf)/is',
            message: 'CSRF token missing: Add @csrf directive to form submissions.',
            contexts: ['view', 'blade', 'general'],
            severity: 'high',
        ));

        // Insecure File Operations
        $this->registerRule(new SecurityRule(
            id: 'path-traversal',
            pattern: '/file_(?:get_contents|put_contents)\s*\([^)]*\$_(?:GET|POST|REQUEST)/i',
            message: 'Path traversal vulnerability: Never use user input directly in file operations.',
            contexts: ['controller', 'general'],
            severity: 'critical',
        ));

        // Weak Cryptography
        $this->registerRule(new SecurityRule(
            id: 'weak-crypto-md5',
            pattern: '/md5\s*\([^)]*\$(?!_)/i',
            message: 'Weak cryptography: MD5 is cryptographically broken. Use bcrypt or Argon2 for passwords.',
            contexts: ['controller', 'model', 'authentication', 'general'],
            severity: 'high',
        ));

        $this->registerRule(new SecurityRule(
            id: 'weak-crypto-sha1',
            pattern: '/sha1\s*\([^)]*\$(?!_)/i',
            message: 'Weak cryptography: SHA1 is deprecated for security. Use Hash facade with bcrypt/argon2.',
            contexts: ['controller', 'model', 'authentication', 'general'],
            severity: 'high',
        ));

        // Debug in Production
        $this->registerRule(new SecurityRule(
            id: 'debug-dd',
            pattern: '/\bdd\s*\(/i',
            message: 'Debug function dd() found. Remove before production deployment.',
            contexts: ['controller', 'model', 'general'],
            severity: 'medium',
        ));

        $this->registerRule(new SecurityRule(
            id: 'debug-dump',
            pattern: '/\bdump\s*\(/i',
            message: 'Debug function dump() found. Remove before production deployment.',
            contexts: ['controller', 'model', 'general'],
            severity: 'medium',
        ));

        // Insecure Direct Object Reference
        $this->registerRule(new SecurityRule(
            id: 'idor-findorfail',
            pattern: '/Route::(?:get|post|put|patch|delete)\s*\([^)]*{id}[^)]*\).*(?!->can\(|Gate::|Policy)/is',
            message: 'Potential IDOR: Ensure authorization is checked when accessing resources by ID.',
            contexts: ['route', 'controller', 'general'],
            severity: 'medium',
        ));

        // Session Fixation
        $this->registerRule(new SecurityRule(
            id: 'session-regenerate',
            pattern: '/Auth::(?:login|attempt)\s*\([^;]*;(?!\s*(?:session\(\)->regenerate|request\(\)->session\(\)->regenerate))/is',
            message: 'Session fixation risk: Regenerate session after authentication with session()->regenerate().',
            contexts: ['controller', 'authentication', 'general'],
            severity: 'high',
        ));

        // Sensitive Data Exposure
        $this->registerRule(new SecurityRule(
            id: 'sensitive-logging',
            pattern: '/Log::(?:info|debug|notice|warning|error|critical|alert|emergency)\s*\([^)]*(?:password|credit|ssn|secret)/i',
            message: 'Sensitive data in logs: Never log passwords, credit cards, or other PII.',
            contexts: ['controller', 'general'],
            severity: 'high',
        ));

        // Header Injection
        $this->registerRule(new SecurityRule(
            id: 'header-injection',
            pattern: '/header\s*\([^)]*\$_(?:GET|POST|REQUEST)/i',
            message: 'Header injection: Never use user input directly in HTTP headers.',
            contexts: ['controller', 'general'],
            severity: 'critical',
        ));

        // Open Redirect
        $this->registerRule(new SecurityRule(
            id: 'open-redirect',
            pattern: '/redirect\s*\(\s*(?:request|input)\s*\(\s*["\'](?:url|redirect|next|return)["\']/',
            message: 'Open redirect vulnerability: Validate redirect URLs against a whitelist.',
            contexts: ['controller', 'general'],
            severity: 'high',
        ));

        // Eval Usage
        $this->registerRule(new SecurityRule(
            id: 'eval-usage',
            pattern: '/\beval\s*\(/i',
            message: 'Critical: eval() is extremely dangerous. Never use it with any user-controlled data.',
            contexts: ['controller', 'general'],
            severity: 'critical',
        ));
    }

    /**
     * @return array<string, string>
     */
    private function getControllerRecommendations(): array
    {
        return [
            'authorization' => 'Always use Policies or Gates for authorization checks',
            'validation' => 'Use Form Request classes for complex validation',
            'mass_assignment' => 'Use $request->validated() instead of $request->all()',
            'sql' => 'Use Eloquent or Query Builder with parameter binding',
            'output' => 'Never directly output user input without escaping',
            'files' => 'Validate file uploads: type, size, and store outside webroot',
            'rate_limiting' => 'Apply rate limiting to sensitive endpoints',
            'logging' => 'Log security events but never log sensitive data',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getModelRecommendations(): array
    {
        return [
            'fillable' => 'Always define $fillable with specific attributes',
            'hidden' => 'Use $hidden for sensitive attributes like passwords',
            'casts' => 'Cast attributes to appropriate types',
            'encryption' => 'Use encrypted casts for sensitive data',
            'accessors' => 'Sanitize data in accessors if outputting to views',
            'scopes' => 'Use global scopes for multi-tenant isolation',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getRequestRecommendations(): array
    {
        return [
            'authorize' => 'Implement authorize() method for access control',
            'validation' => 'Use strict validation rules with proper types',
            'sanitization' => 'Sanitize input after validation when needed',
            'file_validation' => 'Validate file types, sizes, and use mimes rule',
            'array_validation' => 'Limit array sizes to prevent DoS attacks',
            'rate_limiting' => 'Consider rate limiting in authorize() for sensitive forms',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getMigrationRecommendations(): array
    {
        return [
            'indexes' => 'Add indexes for frequently queried columns',
            'foreign_keys' => 'Use foreign key constraints for referential integrity',
            'nullable' => 'Be explicit about nullable columns',
            'defaults' => 'Set sensible defaults for security flags',
            'soft_deletes' => 'Consider soft deletes for audit trail',
            'encryption' => 'Mark columns that should be encrypted in the model',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getApiRecommendations(): array
    {
        return [
            'authentication' => 'Use Sanctum or Passport for API authentication',
            'rate_limiting' => 'Apply rate limiting to all API endpoints',
            'cors' => 'Configure CORS headers explicitly',
            'versioning' => 'Version your API to manage breaking changes',
            'response' => 'Never expose internal errors in API responses',
            'pagination' => 'Always paginate list endpoints to prevent DoS',
            'filtering' => 'Validate and sanitize all query parameters',
            'throttling' => 'Implement exponential backoff for failed auth attempts',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getAuthRecommendations(): array
    {
        return [
            'hashing' => 'Use bcrypt or Argon2 via Hash facade',
            'session' => 'Regenerate session after login',
            'remember' => 'Use secure remember tokens with expiration',
            'lockout' => 'Implement account lockout after failed attempts',
            'mfa' => 'Consider multi-factor authentication for sensitive actions',
            'password' => 'Enforce strong password policies',
            'logout' => 'Invalidate all sessions on password change',
            'tokens' => 'Expire API tokens after reasonable periods',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getQueryRecommendations(): array
    {
        return [
            'binding' => 'Always use parameter binding for user input',
            'eloquent' => 'Prefer Eloquent over raw queries',
            'raw' => 'If using DB::raw(), ensure proper escaping',
            'pagination' => 'Paginate results to prevent memory exhaustion',
            'eager_loading' => 'Use eager loading to prevent N+1 queries',
            'select' => 'Select only needed columns to minimize data exposure',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getGeneralRecommendations(): array
    {
        return [
            'environment' => 'Never commit .env files; use environment variables',
            'debug' => 'Disable debug mode in production',
            'https' => 'Force HTTPS in production',
            'headers' => 'Set security headers (CSP, X-Frame-Options, etc.)',
            'dependencies' => 'Keep dependencies updated; run composer audit',
            'logging' => 'Enable security logging and monitoring',
            'backup' => 'Implement secure backup strategies',
            'encryption' => 'Use Laravel encryption for sensitive data at rest',
        ];
    }
}
