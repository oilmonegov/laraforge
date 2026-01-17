<?php

declare(strict_types=1);

namespace LaraForge\Security;

/**
 * Frontend Security Guard
 *
 * Ensures frontend code follows security best practices.
 * Handles Vue, React, Blade, and JavaScript security patterns.
 */
final class FrontendSecurityGuard
{
    /**
     * @var array<string, string>
     */
    private array $violations = [];

    /**
     * Validate frontend code for security issues.
     *
     * @return array<string, string>
     */
    public function validate(string $code, string $framework = 'blade'): array
    {
        $this->violations = [];

        $this->checkXssVulnerabilities($code, $framework);
        $this->checkCsrfProtection($code, $framework);
        $this->checkSensitiveDataExposure($code, $framework);
        $this->checkInsecurePatterns($code, $framework);

        return $this->violations;
    }

    /**
     * Check if code passes security validation.
     */
    public function passes(string $code, string $framework = 'blade'): bool
    {
        return \count($this->validate($code, $framework)) === 0;
    }

    /**
     * Get security recommendations for frontend framework.
     *
     * @return array<string, array<string, string>>
     */
    public function getRecommendations(string $framework): array
    {
        return match ($framework) {
            'blade' => $this->getBladeRecommendations(),
            'vue' => $this->getVueRecommendations(),
            'react' => $this->getReactRecommendations(),
            'livewire' => $this->getLivewireRecommendations(),
            'inertia' => $this->getInertiaRecommendations(),
            'alpine' => $this->getAlpineRecommendations(),
            default => $this->getGeneralRecommendations(),
        };
    }

    /**
     * Get Content Security Policy recommendations.
     *
     * @return array<string, string>
     */
    public function getCspRecommendations(): array
    {
        return [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' for development only",
            'style-src' => "'self' 'unsafe-inline' for Tailwind",
            'img-src' => "'self' data: https:",
            'font-src' => "'self'",
            'connect-src' => "'self' wss: for WebSocket",
            'frame-ancestors' => "'none'",
            'form-action' => "'self'",
            'base-uri' => "'self'",
            'upgrade-insecure-requests' => 'Enable in production',
        ];
    }

    /**
     * Get security headers recommendations.
     *
     * @return array<string, string>
     */
    public function getSecurityHeaders(): array
    {
        return [
            'X-Frame-Options' => 'DENY or SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];
    }

    private function checkXssVulnerabilities(string $code, string $framework): void
    {
        // Blade unescaped output
        if ($framework === 'blade' && preg_match('/\{!!\s*\$(?!trusted|safe|html)/i', $code)) {
            $this->violations['xss-blade-unescaped'] =
                'Unescaped Blade output {!! !!} - only use for trusted HTML content.';
        }

        // Vue v-html directive
        if ($framework === 'vue' && preg_match('/v-html\s*=\s*["\'][^"\']*\$/i', $code)) {
            $this->violations['xss-vue-vhtml'] =
                'v-html with dynamic content - sanitize with DOMPurify before rendering.';
        }

        // React dangerouslySetInnerHTML
        if ($framework === 'react' && preg_match('/dangerouslySetInnerHTML/i', $code)) {
            $this->violations['xss-react-innerhtml'] =
                'dangerouslySetInnerHTML detected - sanitize with DOMPurify before rendering.';
        }

        // JavaScript innerHTML
        if (preg_match('/\.innerHTML\s*=\s*(?!\s*["\'])/i', $code)) {
            $this->violations['xss-innerhtml'] =
                'Direct innerHTML assignment - use textContent or sanitize input.';
        }

        // JavaScript document.write
        if (preg_match('/document\.write\s*\(/i', $code)) {
            $this->violations['xss-document-write'] =
                'document.write() is dangerous - use DOM manipulation methods.';
        }

        // Eval with variables
        if (preg_match('/eval\s*\([^)]*\$/i', $code)) {
            $this->violations['xss-eval'] =
                'eval() with dynamic content is extremely dangerous - never use.';
        }
    }

    private function checkCsrfProtection(string $code, string $framework): void
    {
        // Blade forms without @csrf
        if ($framework === 'blade') {
            if (preg_match('/<form[^>]*method\s*=\s*["\'](?:post|put|patch|delete)["\'][^>]*>/is', $code)) {
                if (! preg_match('/@csrf/i', $code)) {
                    $this->violations['csrf-missing'] =
                        'Form without @csrf directive - add CSRF protection.';
                }
            }
        }

        // Axios/Fetch without CSRF token
        if (preg_match('/axios\.(?:post|put|patch|delete)\s*\(/i', $code)) {
            if (! preg_match('/X-CSRF-TOKEN|csrf[-_]?token|_token/i', $code)) {
                $this->violations['csrf-ajax'] =
                    'AJAX request may need CSRF token - ensure axios defaults are configured.';
            }
        }
    }

    private function checkSensitiveDataExposure(string $code, string $framework): void
    {
        // API keys in frontend code
        if (preg_match('/["\'](?:api[_-]?key|secret|password|token)\s*["\']?\s*[:=]\s*["\'][a-zA-Z0-9]{20,}/i', $code)) {
            $this->violations['sensitive-hardcoded'] =
                'Sensitive data hardcoded in frontend - use environment variables.';
        }

        // Exposing internal IDs
        if (preg_match('/(?:user_id|admin|internal|private)[_-]?\w*\s*[:=]/i', $code)) {
            $this->violations['sensitive-ids'] =
                'Consider using UUIDs or hashids instead of exposing internal IDs.';
        }

        // Console.log with sensitive data
        if (preg_match('/console\.log\s*\([^)]*(?:password|token|secret|key)/i', $code)) {
            $this->violations['sensitive-logging'] =
                'Logging sensitive data to console - remove before production.';
        }

        // Framework-specific sensitive data checks
        if ($framework === 'vue' && preg_match('/data\s*\(\)\s*\{[^}]*(?:password|secret|token)\s*:/i', $code)) {
            $this->violations['vue-sensitive-data'] =
                'Sensitive data in Vue component data - consider using Pinia store with encryption.';
        }

        if ($framework === 'react' && preg_match('/useState\s*\([^)]*(?:password|secret|token)/i', $code)) {
            $this->violations['react-sensitive-state'] =
                'Sensitive data in React state - consider secure storage patterns.';
        }
    }

    private function checkInsecurePatterns(string $code, string $framework): void
    {
        // localStorage for sensitive data
        if (preg_match('/localStorage\.setItem\s*\([^)]*(?:token|password|secret)/i', $code)) {
            $this->violations['storage-sensitive'] =
                'Storing sensitive data in localStorage - use httpOnly cookies instead.';
        }

        // Inline event handlers
        if (preg_match('/on(?:click|submit|load|error)\s*=\s*["\'][^"\']*\$/i', $code)) {
            $this->violations['inline-handlers'] =
                'Inline event handlers with dynamic content - use addEventListener.';
        }

        // URL construction without encoding
        if (preg_match('/(?:href|src|action)\s*=\s*["\'](?!\s*(?:https?:|\/|#|{{|{%)).*\$[^"\']*["\']/i', $code)) {
            $this->violations['url-encoding'] =
                'Dynamic URL construction - ensure proper URL encoding.';
        }

        // postMessage without origin check
        if (preg_match('/addEventListener\s*\(\s*["\']message["\']/i', $code)) {
            if (! preg_match('/(?:event|e)\.origin/i', $code)) {
                $this->violations['postmessage-origin'] =
                    'postMessage listener without origin verification - add origin check.';
            }
        }

        // Framework-specific insecure patterns
        if ($framework === 'livewire' && preg_match('/wire:model(?!\.defer|\.lazy)/i', $code)) {
            $this->violations['livewire-model-sync'] =
                'Consider wire:model.defer for forms to reduce server requests.';
        }

        if ($framework === 'inertia' && preg_match('/usePage\(\)\.props\./i', $code)) {
            $this->violations['inertia-props-access'] =
                'Direct props access - ensure sensitive data is not exposed in shared props.';
        }

        if ($framework === 'alpine' && preg_match('/x-data\s*=\s*["\'][^"\']*eval/i', $code)) {
            $this->violations['alpine-eval'] =
                'eval() in Alpine.js x-data - extremely dangerous, never use.';
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getBladeRecommendations(): array
    {
        return [
            'output' => [
                'escaped' => 'Use {{ }} for escaped output (default)',
                'unescaped' => 'Only use {!! !!} for trusted, sanitized HTML',
                'json' => 'Use @json() directive for JavaScript data',
            ],
            'forms' => [
                'csrf' => 'Always include @csrf in forms',
                'method' => 'Use @method() for PUT, PATCH, DELETE',
                'validation' => 'Display errors with @error directive',
            ],
            'includes' => [
                'components' => 'Use Blade components for reusable UI',
                'slots' => 'Use named slots for flexible layouts',
                'props' => 'Validate component props',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getVueRecommendations(): array
    {
        return [
            'rendering' => [
                'v-text' => 'Prefer v-text over v-html for text content',
                'v-html' => 'Sanitize content with DOMPurify before v-html',
                'mustache' => 'Use {{ }} for escaped interpolation',
            ],
            'data' => [
                'props' => 'Validate props with type and validator',
                'computed' => 'Use computed for derived state',
                'expose' => 'Limit exposed methods with defineExpose',
            ],
            'security' => [
                'axios' => 'Configure axios interceptors for CSRF',
                'router' => 'Use navigation guards for authorization',
                'store' => 'Avoid storing sensitive data in Pinia/Vuex',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getReactRecommendations(): array
    {
        return [
            'rendering' => [
                'jsx' => 'JSX escapes by default - safe for most cases',
                'innerhtml' => 'Avoid dangerouslySetInnerHTML, use DOMPurify if needed',
                'refs' => 'Prefer refs over direct DOM manipulation',
            ],
            'data' => [
                'props' => 'Use PropTypes or TypeScript for prop validation',
                'context' => 'Avoid sensitive data in React Context',
                'state' => 'Sanitize user input before setting state',
            ],
            'security' => [
                'fetch' => 'Include credentials and CSRF token in fetch calls',
                'urls' => 'Validate URLs before navigation',
                'packages' => 'Audit npm packages regularly',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getLivewireRecommendations(): array
    {
        return [
            'properties' => [
                'public' => 'Be careful with public properties - they are sent to frontend',
                'locked' => 'Use #[Locked] for properties that should not be modified',
                'computed' => 'Use computed properties for derived data',
            ],
            'actions' => [
                'validation' => 'Always validate in action methods',
                'authorization' => 'Check authorization in action methods',
                'rate_limiting' => 'Apply rate limiting to sensitive actions',
            ],
            'security' => [
                'wire:model' => 'Validate all wire:model bound data',
                'wire:click' => 'Ensure actions are authorized server-side',
                'uploads' => 'Validate file uploads with WithFileUploads',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getInertiaRecommendations(): array
    {
        return [
            'props' => [
                'shared' => 'Limit shared data to non-sensitive information',
                'lazy' => 'Use lazy props for large datasets',
                'partial' => 'Use partial reloads for better performance',
            ],
            'authorization' => [
                'middleware' => 'Use middleware for route authorization',
                'policies' => 'Pass authorization results as props',
                'gates' => 'Never rely on frontend for access control',
            ],
            'security' => [
                'csrf' => 'CSRF is handled automatically with axios',
                'validation' => 'Handle validation errors with useForm',
                'redirects' => 'Validate redirect URLs server-side',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getAlpineRecommendations(): array
    {
        return [
            'data' => [
                'x-data' => 'Initialize with functions for complex state',
                'x-init' => 'Avoid sensitive data in x-init',
                'stores' => 'Use Alpine stores for shared state',
            ],
            'rendering' => [
                'x-text' => 'Use x-text for safe text rendering',
                'x-html' => 'Sanitize before using x-html',
                'x-bind' => 'Validate bound values',
            ],
            'events' => [
                'x-on' => 'Validate user input in event handlers',
                'dispatch' => 'Sanitize event payloads',
                'window' => 'Be careful with window-level events',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getGeneralRecommendations(): array
    {
        return [
            'output' => [
                'escape' => 'Always escape user-generated content',
                'sanitize' => 'Use DOMPurify for HTML sanitization',
                'encode' => 'URL-encode dynamic URL components',
            ],
            'storage' => [
                'cookies' => 'Use httpOnly cookies for sensitive tokens',
                'local' => 'Never store passwords/tokens in localStorage',
                'session' => 'sessionStorage cleared on tab close',
            ],
            'communication' => [
                'https' => 'Always use HTTPS in production',
                'cors' => 'Configure CORS headers properly',
                'csp' => 'Implement Content Security Policy',
            ],
        ];
    }
}
