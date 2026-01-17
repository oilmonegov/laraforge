<?php

declare(strict_types=1);

namespace LaraForge\Documentation;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Documentation Sync
 *
 * Fetches and caches documentation from external sources.
 * Supports version-aware fetching and incremental updates.
 */
final class DocumentationSync
{
    private Filesystem $filesystem;

    private DocumentationRegistry $registry;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];

    public function __construct(
        private readonly string $cachePath,
        ?DocumentationRegistry $registry = null,
    ) {
        $this->filesystem = new Filesystem;
        $this->registry = $registry ?? DocumentationRegistry::create();
        $this->loadCache();
    }

    /**
     * Fetch documentation from a source.
     *
     * @return array<string, mixed>
     */
    public function fetch(string $sourceId, string $endpoint, ?string $version = null): array
    {
        $source = $this->registry->get($sourceId);

        if ($source === null) {
            return ['error' => "Unknown source: {$sourceId}"];
        }

        $cacheKey = $this->getCacheKey($sourceId, $endpoint, $version);

        // Check cache first
        if ($this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey]['data'];
        }

        // Fetch from source
        $url = $source->buildUrl($endpoint, $version);
        $result = $this->fetchUrl($url, $source->getMetadata());

        // Cache the result
        $this->cacheResult($cacheKey, $result, $sourceId, $endpoint, $version);

        return $result;
    }

    /**
     * Get cached documentation.
     *
     * @return array<string, mixed>|null
     */
    public function getCached(string $sourceId, string $endpoint, ?string $version = null): ?array
    {
        $cacheKey = $this->getCacheKey($sourceId, $endpoint, $version);

        return $this->cache[$cacheKey]['data'] ?? null;
    }

    /**
     * Check if documentation needs update.
     */
    public function needsUpdate(string $sourceId, string $endpoint, ?string $version = null): bool
    {
        $cacheKey = $this->getCacheKey($sourceId, $endpoint, $version);

        return ! $this->isCacheValid($cacheKey);
    }

    /**
     * Force refresh documentation from source.
     *
     * @return array<string, mixed>
     */
    public function refresh(string $sourceId, string $endpoint, ?string $version = null): array
    {
        $cacheKey = $this->getCacheKey($sourceId, $endpoint, $version);

        // Remove from cache
        unset($this->cache[$cacheKey]);

        // Fetch fresh
        return $this->fetch($sourceId, $endpoint, $version);
    }

    /**
     * Get all cached documentation for a source.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllCached(string $sourceId): array
    {
        $prefix = "{$sourceId}:";
        $results = [];

        foreach ($this->cache as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Get cache status for all sources.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCacheStatus(): array
    {
        $status = [];

        foreach ($this->cache as $key => $value) {
            $parts = explode(':', $key);
            $sourceId = $parts[0];

            if (! isset($status[$sourceId])) {
                $status[$sourceId] = [
                    'source' => $sourceId,
                    'cached_endpoints' => 0,
                    'last_updated' => null,
                    'needs_update' => false,
                ];
            }

            $status[$sourceId]['cached_endpoints']++;

            $cachedAt = $value['cached_at'] ?? null;
            if ($cachedAt !== null) {
                $current = $status[$sourceId]['last_updated'];
                if ($current === null || $cachedAt > $current) {
                    $status[$sourceId]['last_updated'] = $cachedAt;
                }
            }

            if (! $this->isCacheValid($key)) {
                $status[$sourceId]['needs_update'] = true;
            }
        }

        return $status;
    }

    /**
     * Clear cache for a source or all sources.
     */
    public function clearCache(?string $sourceId = null): void
    {
        if ($sourceId === null) {
            $this->cache = [];
        } else {
            $prefix = "{$sourceId}:";
            $this->cache = array_filter(
                $this->cache,
                fn ($key) => ! str_starts_with($key, $prefix),
                ARRAY_FILTER_USE_KEY
            );
        }

        $this->saveCache();
    }

    /**
     * Get documentation URL for manual reference.
     */
    public function getDocUrl(string $sourceId, string $endpoint, ?string $version = null): ?string
    {
        $source = $this->registry->get($sourceId);

        if ($source === null) {
            return null;
        }

        return $source->buildUrl($endpoint, $version);
    }

    /**
     * Fetch package info from Packagist.
     *
     * @return array<string, mixed>
     */
    public function fetchPackageInfo(string $vendor, string $package): array
    {
        $url = "https://packagist.org/packages/{$vendor}/{$package}.json";
        $result = $this->fetchUrl($url, ['type' => 'json']);

        return $result['package'] ?? $result;
    }

    /**
     * Fetch latest release from GitHub.
     *
     * @return array<string, mixed>
     */
    public function fetchLatestRelease(string $owner, string $repo): array
    {
        $url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";

        return $this->fetchUrl($url, [
            'type' => 'json',
            'headers' => ['Accept: application/vnd.github.v3+json'],
        ]);
    }

    /**
     * Fetch README from GitHub.
     */
    public function fetchReadme(string $owner, string $repo): string
    {
        $url = "https://api.github.com/repos/{$owner}/{$repo}/readme";
        $result = $this->fetchUrl($url, [
            'type' => 'json',
            'headers' => ['Accept: application/vnd.github.v3+json'],
        ]);

        if (isset($result['content'])) {
            return base64_decode($result['content']);
        }

        return '';
    }

    /**
     * Get the documentation registry.
     */
    public function getRegistry(): DocumentationRegistry
    {
        return $this->registry;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function fetchUrl(string $url, array $metadata = []): array
    {
        $context = $this->createStreamContext($metadata);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            return [
                'error' => 'Failed to fetch',
                'url' => $url,
            ];
        }

        $type = $metadata['type'] ?? 'html';

        if ($type === 'json') {
            $decoded = json_decode($content, true);

            return $decoded ?? ['raw' => $content];
        }

        return [
            'content' => $content,
            'url' => $url,
            'fetched_at' => date('c'),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return resource
     */
    private function createStreamContext(array $metadata)
    {
        $headers = $metadata['headers'] ?? [];
        $headers[] = 'User-Agent: LaraForge/1.0';

        return stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 30,
            ],
        ]);
    }

    private function getCacheKey(string $sourceId, string $endpoint, ?string $version): string
    {
        $key = "{$sourceId}:{$endpoint}";

        if ($version !== null) {
            $key .= ":{$version}";
        }

        return $key;
    }

    private function isCacheValid(string $key): bool
    {
        if (! isset($this->cache[$key])) {
            return false;
        }

        $cachedAt = $this->cache[$key]['cached_at'] ?? null;

        if ($cachedAt === null) {
            return false;
        }

        // Cache expires after 24 hours
        $expiresAt = strtotime($cachedAt) + 86400;

        return time() < $expiresAt;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function cacheResult(
        string $key,
        array $result,
        string $sourceId,
        string $endpoint,
        ?string $version
    ): void {
        $this->cache[$key] = [
            'source' => $sourceId,
            'endpoint' => $endpoint,
            'version' => $version,
            'data' => $result,
            'cached_at' => date('c'),
        ];

        $this->saveCache();
    }

    private function loadCache(): void
    {
        $cacheFile = $this->cachePath.'/documentation_cache.json';

        if ($this->filesystem->exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            if ($content !== false) {
                $this->cache = json_decode($content, true) ?? [];
            }
        }
    }

    private function saveCache(): void
    {
        if (! $this->filesystem->exists($this->cachePath)) {
            $this->filesystem->mkdir($this->cachePath);
        }

        $cacheFile = $this->cachePath.'/documentation_cache.json';
        file_put_contents(
            $cacheFile,
            json_encode($this->cache, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Create from a project path.
     */
    public static function fromPath(string $projectPath): self
    {
        return new self($projectPath.'/.laraforge/cache');
    }
}
