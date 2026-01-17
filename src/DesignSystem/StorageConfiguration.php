<?php

declare(strict_types=1);

namespace LaraForge\DesignSystem;

/**
 * Storage Configuration
 *
 * Defines S3-compatible storage configuration for uploads.
 * Supports AWS S3, DigitalOcean Spaces, MinIO, and other S3-compatible services.
 */
final class StorageConfiguration
{
    /**
     * Supported storage providers.
     *
     * @var array<string, array<string, mixed>>
     */
    private const PROVIDERS = [
        's3' => [
            'name' => 'Amazon S3',
            'driver' => 's3',
            'envPrefix' => 'AWS',
            'cdnSupport' => true,
            'config' => [
                'key' => 'AWS_ACCESS_KEY_ID',
                'secret' => 'AWS_SECRET_ACCESS_KEY',
                'region' => 'AWS_DEFAULT_REGION',
                'bucket' => 'AWS_BUCKET',
                'url' => 'AWS_URL',
                'endpoint' => 'AWS_ENDPOINT',
                'use_path_style_endpoint' => 'AWS_USE_PATH_STYLE_ENDPOINT',
            ],
        ],
        'digitalocean' => [
            'name' => 'DigitalOcean Spaces',
            'driver' => 's3',
            'envPrefix' => 'DO',
            'cdnSupport' => true,
            'config' => [
                'key' => 'DO_SPACES_KEY',
                'secret' => 'DO_SPACES_SECRET',
                'region' => 'DO_SPACES_REGION',
                'bucket' => 'DO_SPACES_BUCKET',
                'endpoint' => 'DO_SPACES_ENDPOINT',
                'cdn_endpoint' => 'DO_SPACES_CDN_ENDPOINT',
            ],
        ],
        'minio' => [
            'name' => 'MinIO',
            'driver' => 's3',
            'envPrefix' => 'MINIO',
            'cdnSupport' => false,
            'config' => [
                'key' => 'MINIO_ACCESS_KEY',
                'secret' => 'MINIO_SECRET_KEY',
                'region' => 'MINIO_REGION',
                'bucket' => 'MINIO_BUCKET',
                'endpoint' => 'MINIO_ENDPOINT',
                'use_path_style_endpoint' => true,
            ],
        ],
        'cloudflare-r2' => [
            'name' => 'Cloudflare R2',
            'driver' => 's3',
            'envPrefix' => 'CLOUDFLARE',
            'cdnSupport' => true,
            'config' => [
                'key' => 'CLOUDFLARE_R2_ACCESS_KEY_ID',
                'secret' => 'CLOUDFLARE_R2_SECRET_ACCESS_KEY',
                'bucket' => 'CLOUDFLARE_R2_BUCKET',
                'endpoint' => 'CLOUDFLARE_R2_ENDPOINT',
            ],
        ],
        'backblaze' => [
            'name' => 'Backblaze B2',
            'driver' => 's3',
            'envPrefix' => 'B2',
            'cdnSupport' => true,
            'config' => [
                'key' => 'B2_ACCESS_KEY_ID',
                'secret' => 'B2_SECRET_ACCESS_KEY',
                'region' => 'B2_REGION',
                'bucket' => 'B2_BUCKET',
                'endpoint' => 'B2_ENDPOINT',
            ],
        ],
    ];

    /**
     * Upload categories with security settings.
     *
     * @var array<string, array<string, mixed>>
     */
    private const UPLOAD_CATEGORIES = [
        'public' => [
            'visibility' => 'public',
            'path' => 'uploads/public',
            'maxSize' => 10485760, // 10MB
            'allowedTypes' => ['image/*', 'video/*', 'application/pdf'],
            'generateThumbnails' => true,
            'cdnEnabled' => true,
        ],
        'private' => [
            'visibility' => 'private',
            'path' => 'uploads/private',
            'maxSize' => 52428800, // 50MB
            'allowedTypes' => ['*'],
            'generateThumbnails' => false,
            'cdnEnabled' => false,
            'signedUrls' => true,
            'signedUrlExpiry' => 3600, // 1 hour
        ],
        'avatars' => [
            'visibility' => 'public',
            'path' => 'uploads/avatars',
            'maxSize' => 2097152, // 2MB
            'allowedTypes' => ['image/jpeg', 'image/png', 'image/webp'],
            'generateThumbnails' => true,
            'thumbnailSizes' => [
                'sm' => [64, 64],
                'md' => [128, 128],
                'lg' => [256, 256],
            ],
            'cdnEnabled' => true,
        ],
        'documents' => [
            'visibility' => 'private',
            'path' => 'uploads/documents',
            'maxSize' => 104857600, // 100MB
            'allowedTypes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'generateThumbnails' => false,
            'signedUrls' => true,
            'virusScan' => true,
        ],
        'signatures' => [
            'visibility' => 'private',
            'path' => 'uploads/signatures',
            'maxSize' => 1048576, // 1MB
            'allowedTypes' => ['image/png', 'image/svg+xml'],
            'generateThumbnails' => false,
            'signedUrls' => true,
            'signedUrlExpiry' => 300, // 5 minutes
            'encrypted' => true,
        ],
        'temp' => [
            'visibility' => 'private',
            'path' => 'uploads/temp',
            'maxSize' => 104857600, // 100MB
            'allowedTypes' => ['*'],
            'autoDelete' => true,
            'autoDeleteAfter' => 86400, // 24 hours
        ],
    ];

    public function __construct(
        private readonly string $defaultProvider = 's3',
        private readonly string $defaultCategory = 'public',
    ) {}

    /**
     * Get all available storage providers.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getProviders(): array
    {
        return self::PROVIDERS;
    }

    /**
     * Get a specific provider configuration.
     *
     * @return array<string, mixed>|null
     */
    public function getProvider(string $provider): ?array
    {
        return self::PROVIDERS[$provider] ?? null;
    }

    /**
     * Get all upload categories.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCategories(): array
    {
        return self::UPLOAD_CATEGORIES;
    }

    /**
     * Get a specific upload category.
     *
     * @return array<string, mixed>|null
     */
    public function getCategory(string $category): ?array
    {
        return self::UPLOAD_CATEGORIES[$category] ?? null;
    }

    /**
     * Generate Laravel filesystem configuration.
     *
     * @return array<string, mixed>
     */
    public function generateFilesystemConfig(?string $provider = null): array
    {
        $provider = $provider ?? $this->defaultProvider;
        $config = $this->getProvider($provider);

        if ($config === null) {
            return [];
        }

        $diskConfig = [
            'driver' => $config['driver'],
            'throw' => true,
        ];

        foreach ($config['config'] as $key => $envVar) {
            if (is_string($envVar)) {
                $diskConfig[$key] = "env('{$envVar}')";
            } else {
                $diskConfig[$key] = $envVar;
            }
        }

        return [
            'disk_name' => $provider,
            'config' => $diskConfig,
        ];
    }

    /**
     * Generate .env template for a provider.
     */
    public function generateEnvTemplate(?string $provider = null): string
    {
        $provider = $provider ?? $this->defaultProvider;
        $config = $this->getProvider($provider);

        if ($config === null) {
            return '';
        }

        $lines = ["# {$config['name']} Configuration"];

        foreach ($config['config'] as $key => $envVar) {
            if (is_string($envVar)) {
                $lines[] = "{$envVar}=";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get upload validation rules for a category.
     *
     * @return array<string, mixed>
     */
    public function getValidationRules(string $category): array
    {
        $config = $this->getCategory($category);

        if ($config === null) {
            return [];
        }

        $rules = [
            'max' => $config['maxSize'] / 1024, // Convert to KB for Laravel
        ];

        $mimes = [];
        foreach ($config['allowedTypes'] as $type) {
            if ($type === '*') {
                continue;
            }
            if (str_contains($type, '*')) {
                // Handle wildcards like 'image/*'
                $mimes[] = str_replace('/*', '', $type);
            } else {
                // Convert MIME to extension
                $mimes[] = $this->mimeToExtension($type);
            }
        }

        if ($mimes !== []) {
            $rules['mimes'] = implode(',', array_filter($mimes));
        }

        return $rules;
    }

    /**
     * Get storage path for a category.
     */
    public function getStoragePath(string $category, string $filename = ''): string
    {
        $config = $this->getCategory($category);
        $basePath = $config['path'] ?? 'uploads';

        if ($filename === '') {
            return $basePath;
        }

        return $basePath.'/'.$filename;
    }

    /**
     * Check if category supports CDN.
     */
    public function supportsCdn(string $category): bool
    {
        $config = $this->getCategory($category);

        return $config['cdnEnabled'] ?? false;
    }

    /**
     * Check if category requires signed URLs.
     */
    public function requiresSignedUrl(string $category): bool
    {
        $config = $this->getCategory($category);

        return $config['signedUrls'] ?? false;
    }

    /**
     * Get signed URL expiry for a category.
     */
    public function getSignedUrlExpiry(string $category): int
    {
        $config = $this->getCategory($category);

        return $config['signedUrlExpiry'] ?? 3600;
    }

    /**
     * Generate upload service class template.
     */
    public function generateUploadServiceTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class UploadService
{
    public function __construct(
        private readonly string $disk = 's3',
    ) {}

    /**
     * Upload a file to storage.
     *
     * @return array{path: string, url: string, size: int, mime: string}
     */
    public function upload(
        UploadedFile $file,
        string $category = 'public',
        ?string $customPath = null,
    ): array {
        $path = $customPath ?? $this->generatePath($category, $file);

        $storedPath = Storage::disk($this->disk)->putFileAs(
            dirname($path),
            $file,
            basename($path),
            $this->getVisibility($category),
        );

        return [
            'path' => $storedPath,
            'url' => $this->getUrl($storedPath, $category),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }

    /**
     * Get URL for a stored file.
     */
    public function getUrl(string $path, string $category = 'public'): string
    {
        if ($this->requiresSignedUrl($category)) {
            return Storage::disk($this->disk)->temporaryUrl(
                $path,
                now()->addSeconds($this->getSignedUrlExpiry($category)),
            );
        }

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Delete a file from storage.
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    private function generatePath(string $category, UploadedFile $file): string
    {
        $basePath = config("upload.categories.{$category}.path", 'uploads');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        return "{$basePath}/{$filename}";
    }

    private function getVisibility(string $category): string
    {
        return config("upload.categories.{$category}.visibility", 'private');
    }

    private function requiresSignedUrl(string $category): bool
    {
        return config("upload.categories.{$category}.signed_urls", false);
    }

    private function getSignedUrlExpiry(string $category): int
    {
        return config("upload.categories.{$category}.signed_url_expiry", 3600);
    }
}
PHP;
    }

    private function mimeToExtension(string $mime): string
    {
        $map = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'image/jpeg' => 'jpg,jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];

        return $map[$mime] ?? '';
    }

    /**
     * Get the default category.
     */
    public function getDefaultCategory(): string
    {
        return $this->defaultCategory;
    }

    /**
     * Get the default provider.
     */
    public function getDefaultProvider(): string
    {
        return $this->defaultProvider;
    }

    /**
     * Create with defaults.
     */
    public static function create(string $provider = 's3'): self
    {
        return new self($provider);
    }
}
