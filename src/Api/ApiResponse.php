<?php

declare(strict_types=1);

namespace LaraForge\Api;

/**
 * Standardized API Response
 *
 * Provides consistent, secure API response formatting.
 * Strips sensitive information, ensures standard structure,
 * and handles different response states properly.
 */
final class ApiResponse
{
    /**
     * Response states.
     */
    public const STATE_SUCCESS = 'success';

    public const STATE_ERROR = 'error';

    public const STATE_VALIDATION_ERROR = 'validation_error';

    public const STATE_NOT_FOUND = 'not_found';

    public const STATE_UNAUTHORIZED = 'unauthorized';

    public const STATE_FORBIDDEN = 'forbidden';

    public const STATE_RATE_LIMITED = 'rate_limited';

    public const STATE_SERVER_ERROR = 'server_error';

    public const STATE_SERVICE_UNAVAILABLE = 'service_unavailable';

    /**
     * HTTP status codes for states.
     */
    private const STATE_HTTP_CODES = [
        self::STATE_SUCCESS => 200,
        self::STATE_ERROR => 400,
        self::STATE_VALIDATION_ERROR => 422,
        self::STATE_NOT_FOUND => 404,
        self::STATE_UNAUTHORIZED => 401,
        self::STATE_FORBIDDEN => 403,
        self::STATE_RATE_LIMITED => 429,
        self::STATE_SERVER_ERROR => 500,
        self::STATE_SERVICE_UNAVAILABLE => 503,
    ];

    /**
     * Fields that should never be exposed in API responses.
     *
     * @var array<string>
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_hash',
        'secret',
        'api_key',
        'api_secret',
        'token',
        'access_token',
        'refresh_token',
        'private_key',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'social_security',
        'database_url',
        'db_password',
        'encryption_key',
        'app_key',
        'stripe_secret',
        'aws_secret',
        'file_path',
        'server_path',
        'stack_trace',
        'trace',
        'debug',
        'internal_id',
        'internal_notes',
        'admin_notes',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     */
    public function __construct(
        private readonly string $state,
        private readonly string $message,
        private readonly array $data = [],
        private readonly array $meta = [],
        private readonly array $links = [],
        private readonly ?int $httpCode = null,
    ) {}

    public function getState(): string
    {
        return $this->state;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode ?? self::STATE_HTTP_CODES[$this->state] ?? 200;
    }

    public function isSuccess(): bool
    {
        return $this->state === self::STATE_SUCCESS;
    }

    public function isError(): bool
    {
        return $this->state !== self::STATE_SUCCESS;
    }

    /**
     * Convert to array for JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = [
            'success' => $this->isSuccess(),
            'state' => $this->state,
            'message' => $this->message,
        ];

        if (! empty($this->data)) {
            $response['data'] = $this->sanitizeData($this->data);
        }

        if (! empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        if (! empty($this->links)) {
            $response['links'] = $this->links;
        }

        return $response;
    }

    /**
     * Create success response.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     */
    public static function success(
        string $message = 'Operation completed successfully',
        array $data = [],
        array $meta = [],
        array $links = [],
    ): self {
        return new self(
            state: self::STATE_SUCCESS,
            message: $message,
            data: $data,
            meta: $meta,
            links: $links,
            httpCode: 200,
        );
    }

    /**
     * Create created response (201).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $links
     */
    public static function created(
        string $message = 'Resource created successfully',
        array $data = [],
        array $links = [],
    ): self {
        return new self(
            state: self::STATE_SUCCESS,
            message: $message,
            data: $data,
            links: $links,
            httpCode: 201,
        );
    }

    /**
     * Create accepted response (202) for async operations.
     *
     * @param  array<string, mixed>  $data
     */
    public static function accepted(
        string $message = 'Request accepted for processing',
        array $data = [],
    ): self {
        return new self(
            state: self::STATE_SUCCESS,
            message: $message,
            data: $data,
            httpCode: 202,
        );
    }

    /**
     * Create no content response (204).
     */
    public static function noContent(): self
    {
        return new self(
            state: self::STATE_SUCCESS,
            message: '',
            httpCode: 204,
        );
    }

    /**
     * Create error response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function error(
        string $message = 'An error occurred',
        array $data = [],
        int $httpCode = 400,
    ): self {
        return new self(
            state: self::STATE_ERROR,
            message: $message,
            data: $data,
            httpCode: $httpCode,
        );
    }

    /**
     * Create validation error response.
     *
     * @param  array<string, array<string>>  $errors
     */
    public static function validationError(
        string $message = 'Validation failed',
        array $errors = [],
    ): self {
        return new self(
            state: self::STATE_VALIDATION_ERROR,
            message: $message,
            data: ['errors' => $errors],
            httpCode: 422,
        );
    }

    /**
     * Create not found response.
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return new self(
            state: self::STATE_NOT_FOUND,
            message: $message,
            httpCode: 404,
        );
    }

    /**
     * Create unauthorized response.
     */
    public static function unauthorized(string $message = 'Authentication required'): self
    {
        return new self(
            state: self::STATE_UNAUTHORIZED,
            message: $message,
            httpCode: 401,
        );
    }

    /**
     * Create forbidden response.
     */
    public static function forbidden(string $message = 'Access denied'): self
    {
        return new self(
            state: self::STATE_FORBIDDEN,
            message: $message,
            httpCode: 403,
        );
    }

    /**
     * Create rate limited response.
     */
    public static function rateLimited(
        string $message = 'Too many requests',
        int $retryAfter = 60,
    ): self {
        return new self(
            state: self::STATE_RATE_LIMITED,
            message: $message,
            meta: ['retry_after' => $retryAfter],
            httpCode: 429,
        );
    }

    /**
     * Create server error response (safe for production).
     */
    public static function serverError(
        string $message = 'An unexpected error occurred',
        ?string $errorCode = null,
    ): self {
        $data = [];
        if ($errorCode !== null) {
            $data['error_code'] = $errorCode;
        }

        return new self(
            state: self::STATE_SERVER_ERROR,
            message: $message,
            data: $data,
            httpCode: 500,
        );
    }

    /**
     * Create service unavailable response.
     */
    public static function serviceUnavailable(
        string $message = 'Service temporarily unavailable',
        int $retryAfter = 300,
    ): self {
        return new self(
            state: self::STATE_SERVICE_UNAVAILABLE,
            message: $message,
            meta: ['retry_after' => $retryAfter],
            httpCode: 503,
        );
    }

    /**
     * Create paginated response.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $pagination
     */
    public static function paginated(
        array $data,
        array $pagination,
        string $message = 'Data retrieved successfully',
    ): self {
        return new self(
            state: self::STATE_SUCCESS,
            message: $message,
            data: $data,
            meta: [
                'pagination' => [
                    'current_page' => $pagination['current_page'] ?? 1,
                    'per_page' => $pagination['per_page'] ?? 15,
                    'total' => $pagination['total'] ?? 0,
                    'total_pages' => $pagination['total_pages'] ?? 1,
                    'has_more' => $pagination['has_more'] ?? false,
                ],
            ],
            links: [
                'first' => $pagination['first_page_url'] ?? null,
                'last' => $pagination['last_page_url'] ?? null,
                'prev' => $pagination['prev_page_url'] ?? null,
                'next' => $pagination['next_page_url'] ?? null,
            ],
            httpCode: 200,
        );
    }

    /**
     * Sanitize data to remove sensitive fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Skip sensitive fields
            if ($this->isSensitiveField($key)) {
                continue;
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                /** @var array<string, mixed> $nestedArray */
                $nestedArray = $value;
                $sanitized[$key] = $this->sanitizeData($nestedArray);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function isSensitiveField(string $field): bool
    {
        $normalizedField = strtolower($field);

        foreach (self::SENSITIVE_FIELDS as $sensitive) {
            if (str_contains($normalizedField, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get response format specification.
     *
     * @return array<string, mixed>
     */
    public static function getSpecification(): array
    {
        return [
            'standard_format' => [
                'success' => 'boolean - Whether the operation succeeded',
                'state' => 'string - One of the defined states',
                'message' => 'string - Human-readable message',
                'data' => 'object|null - Response payload',
                'meta' => 'object|null - Metadata (pagination, etc.)',
                'links' => 'object|null - Related links',
            ],
            'states' => [
                self::STATE_SUCCESS => 'Operation completed successfully',
                self::STATE_ERROR => 'General error',
                self::STATE_VALIDATION_ERROR => 'Input validation failed',
                self::STATE_NOT_FOUND => 'Resource not found',
                self::STATE_UNAUTHORIZED => 'Authentication required',
                self::STATE_FORBIDDEN => 'Permission denied',
                self::STATE_RATE_LIMITED => 'Too many requests',
                self::STATE_SERVER_ERROR => 'Internal server error',
                self::STATE_SERVICE_UNAVAILABLE => 'Service temporarily unavailable',
            ],
            'http_codes' => self::STATE_HTTP_CODES,
            'sensitive_fields' => 'These fields are automatically stripped from responses',
        ];
    }
}
