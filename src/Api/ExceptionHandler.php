<?php

declare(strict_types=1);

namespace LaraForge\Api;

use Throwable;

/**
 * Exception Handler for API Responses
 *
 * Converts exceptions to safe API responses, ensuring
 * sensitive information is never exposed to clients.
 */
final class ExceptionHandler
{
    /**
     * Exception types that indicate validation errors.
     *
     * @var array<string>
     */
    private const VALIDATION_EXCEPTIONS = [
        'Illuminate\Validation\ValidationException',
        'ValidationException',
    ];

    /**
     * Exception types that indicate authentication errors.
     *
     * @var array<string>
     */
    private const AUTH_EXCEPTIONS = [
        'Illuminate\Auth\AuthenticationException',
        'AuthenticationException',
    ];

    /**
     * Exception types that indicate authorization errors.
     *
     * @var array<string>
     */
    private const AUTHORIZATION_EXCEPTIONS = [
        'Illuminate\Auth\Access\AuthorizationException',
        'Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException',
        'AuthorizationException',
    ];

    /**
     * Exception types that indicate not found.
     *
     * @var array<string>
     */
    private const NOT_FOUND_EXCEPTIONS = [
        'Illuminate\Database\Eloquent\ModelNotFoundException',
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
        'ModelNotFoundException',
        'NotFoundHttpException',
    ];

    /**
     * Exception types that indicate rate limiting.
     *
     * @var array<string>
     */
    private const RATE_LIMIT_EXCEPTIONS = [
        'Illuminate\Http\Exceptions\ThrottleRequestsException',
        'Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException',
        'ThrottleRequestsException',
    ];

    /**
     * Patterns that should be stripped from error messages.
     *
     * @var array<string>
     */
    private const SENSITIVE_PATTERNS = [
        '/SQLSTATE\[[^\]]+\]/',
        '/at\s+\/[^\s]+/',
        '/in\s+\/[^\s]+/',
        '/password[^\s]*/i',
        '/secret[^\s]*/i',
        '/key[^\s]*/i',
        '/token[^\s]*/i',
        '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/',
        '/\/[a-zA-Z\/]+\.php:\d+/',
    ];

    private bool $debug;

    /**
     * @var callable|null
     */
    private $logger;

    public function __construct(
        bool $debug = false,
        ?callable $logger = null,
    ) {
        $this->debug = $debug;
        $this->logger = $logger;
    }

    /**
     * Handle an exception and return a safe API response.
     */
    public function handle(Throwable $exception): ApiResponse
    {
        // Log the exception
        $this->logException($exception);

        // Determine exception type and create appropriate response
        $exceptionClass = get_class($exception);

        if ($this->matchesExceptionType($exceptionClass, self::VALIDATION_EXCEPTIONS)) {
            return $this->handleValidationException($exception);
        }

        if ($this->matchesExceptionType($exceptionClass, self::AUTH_EXCEPTIONS)) {
            return ApiResponse::unauthorized('Authentication required');
        }

        if ($this->matchesExceptionType($exceptionClass, self::AUTHORIZATION_EXCEPTIONS)) {
            return ApiResponse::forbidden('You do not have permission to perform this action');
        }

        if ($this->matchesExceptionType($exceptionClass, self::NOT_FOUND_EXCEPTIONS)) {
            return $this->handleNotFoundException($exception);
        }

        if ($this->matchesExceptionType($exceptionClass, self::RATE_LIMIT_EXCEPTIONS)) {
            return $this->handleRateLimitException($exception);
        }

        // Handle generic exceptions
        return $this->handleGenericException($exception);
    }

    /**
     * Get safe error message for production.
     */
    public function getSafeMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        // In debug mode, allow more detail
        if ($this->debug) {
            return $this->sanitizeMessage($message);
        }

        // In production, use generic messages for server errors
        if ($exception->getCode() >= 500 || $exception->getCode() === 0) {
            return 'An unexpected error occurred. Please try again later.';
        }

        return $this->sanitizeMessage($message);
    }

    /**
     * Get exception context for logging (includes sensitive data).
     *
     * @return array<string, mixed>
     */
    public function getLoggingContext(Throwable $exception): array
    {
        return [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'previous' => $exception->getPrevious() ? [
                'exception' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
            ] : null,
        ];
    }

    /**
     * Create error code from exception for tracking.
     */
    public function createErrorCode(Throwable $exception): string
    {
        $hash = substr(md5(get_class($exception).$exception->getFile().$exception->getLine()), 0, 8);

        return 'ERR_'.strtoupper($hash);
    }

    /**
     * Get response generation patterns for Laravel.
     *
     * @return array<string, mixed>
     */
    public static function getLaravelPatterns(): array
    {
        return [
            'exception_handler' => [
                'description' => 'Add to app/Exceptions/Handler.php render method',
                'code' => <<<'PHP'
                    public function render($request, Throwable $exception)
                    {
                        if ($request->expectsJson() || $request->is('api/*')) {
                            $handler = new \LaraForge\Api\ExceptionHandler(
                                debug: config('app.debug'),
                                logger: fn($context) => \Log::error('API Exception', $context)
                            );

                            $response = $handler->handle($exception);

                            return response()->json(
                                $response->toArray(),
                                $response->getHttpCode()
                            );
                        }

                        return parent::render($request, $exception);
                    }
                    PHP,
            ],
            'api_controller_trait' => [
                'description' => 'Trait for standardized controller responses',
                'code' => <<<'PHP'
                    trait ApiResponses
                    {
                        protected function success(string $message, array $data = []): JsonResponse
                        {
                            return response()->json(
                                ApiResponse::success($message, $data)->toArray(),
                                200
                            );
                        }

                        protected function created(string $message, array $data = []): JsonResponse
                        {
                            return response()->json(
                                ApiResponse::created($message, $data)->toArray(),
                                201
                            );
                        }

                        protected function error(string $message, int $code = 400): JsonResponse
                        {
                            return response()->json(
                                ApiResponse::error($message)->toArray(),
                                $code
                            );
                        }
                    }
                    PHP,
            ],
        ];
    }

    private function handleValidationException(Throwable $exception): ApiResponse
    {
        $errors = [];

        // Try to extract validation errors
        if (method_exists($exception, 'errors')) {
            /** @var array<string, array<string>> $errors */
            $errors = $exception->errors();
        } elseif (method_exists($exception, 'getErrors')) {
            /** @var array<string, array<string>> $errors */
            $errors = $exception->getErrors();
        }

        return ApiResponse::validationError(
            'The given data was invalid.',
            $errors
        );
    }

    private function handleNotFoundException(Throwable $exception): ApiResponse
    {
        $message = 'The requested resource was not found.';

        // Try to get model name for better messages
        if (method_exists($exception, 'getModel')) {
            $modelClass = $exception->getModel();
            if (is_string($modelClass)) {
                $parts = explode('\\', $modelClass);
                $model = end($parts);
                $message = "{$model} not found.";
            }
        }

        return ApiResponse::notFound($message);
    }

    private function handleRateLimitException(Throwable $exception): ApiResponse
    {
        $retryAfter = 60;

        if (method_exists($exception, 'getHeaders')) {
            $headers = $exception->getHeaders();
            if (is_array($headers) && isset($headers['Retry-After'])) {
                $retryValue = $headers['Retry-After'];
                $retryAfter = is_numeric($retryValue) ? (int) $retryValue : 60;
            }
        }

        return ApiResponse::rateLimited(
            'Too many requests. Please slow down.',
            $retryAfter
        );
    }

    private function handleGenericException(Throwable $exception): ApiResponse
    {
        $errorCode = $this->createErrorCode($exception);

        if ($this->debug) {
            return ApiResponse::error(
                $this->getSafeMessage($exception),
                [
                    'error_code' => $errorCode,
                    'exception' => get_class($exception),
                    'file' => basename($exception->getFile()),
                    'line' => $exception->getLine(),
                ],
                500
            );
        }

        return ApiResponse::serverError(
            'An unexpected error occurred. Please contact support if the problem persists.',
            $errorCode
        );
    }

    private function sanitizeMessage(string $message): string
    {
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message) ?? $message;
        }

        return $message;
    }

    /**
     * @param  array<string>  $types
     */
    private function matchesExceptionType(string $exceptionClass, array $types): bool
    {
        foreach ($types as $type) {
            if ($exceptionClass === $type || str_ends_with($exceptionClass, '\\'.$type)) {
                return true;
            }
        }

        return false;
    }

    private function logException(Throwable $exception): void
    {
        if ($this->logger !== null) {
            ($this->logger)($this->getLoggingContext($exception));
        }
    }
}
