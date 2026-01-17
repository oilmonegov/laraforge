<?php

declare(strict_types=1);

namespace LaraForge\Documentation;

/**
 * Documentation Source
 *
 * Represents an external documentation source that can be fetched and cached.
 * Sources include Laravel docs, PHP manual, package READMEs, etc.
 */
final class DocumentationSource
{
    /**
     * @param  array<string, string>  $endpoints
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $identifier,
        private readonly string $name,
        private readonly string $baseUrl,
        private readonly array $endpoints = [],
        private readonly ?string $versionPattern = null,
        private readonly array $metadata = [],
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return array<string, string>
     */
    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    public function getEndpoint(string $key): ?string
    {
        return $this->endpoints[$key] ?? null;
    }

    public function getVersionPattern(): ?string
    {
        return $this->versionPattern;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Build full URL for an endpoint with version substitution.
     */
    public function buildUrl(string $endpoint, ?string $version = null): string
    {
        $path = $this->endpoints[$endpoint] ?? $endpoint;
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');

        if ($version !== null && $this->versionPattern !== null) {
            $url = str_replace($this->versionPattern, $version, $url);
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
            'base_url' => $this->baseUrl,
            'endpoints' => $this->endpoints,
            'version_pattern' => $this->versionPattern,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            identifier: $data['identifier'],
            name: $data['name'],
            baseUrl: $data['base_url'],
            endpoints: $data['endpoints'] ?? [],
            versionPattern: $data['version_pattern'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
