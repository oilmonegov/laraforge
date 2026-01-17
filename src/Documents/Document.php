<?php

declare(strict_types=1);

namespace LaraForge\Documents;

use LaraForge\Documents\Contracts\DocumentInterface;

abstract class Document implements DocumentInterface
{
    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        protected string $title,
        protected string $version = '1.0',
        protected string $status = 'draft',
        protected ?string $featureId = null,
        protected array $content = [],
        protected ?string $path = null,
        protected array $metadata = [],
    ) {}

    abstract public function type(): string;

    public function title(): string
    {
        return $this->title;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function featureId(): ?string
    {
        return $this->featureId;
    }

    public function content(): array
    {
        return $this->content;
    }

    public function rawContent(): string
    {
        return $this->serialize();
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function isValid(): bool
    {
        return empty($this->validationErrors());
    }

    public function validationErrors(): array
    {
        $errors = [];

        if (empty($this->title)) {
            $errors[] = 'Title is required';
        }

        if (! in_array($this->status, $this->validStatuses(), true)) {
            $errors[] = 'Invalid status: '.$this->status;
        }

        return array_merge($errors, $this->validateContent());
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title,
            'version' => $this->version,
            'status' => $this->status,
            'feature_id' => $this->featureId,
            'content' => $this->content,
            'metadata' => $this->metadata,
        ];
    }

    public function serialize(): string
    {
        return yaml_emit($this->toArray());
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setFeatureId(string $featureId): void
    {
        $this->featureId = $featureId;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * @return array<string>
     */
    protected function validStatuses(): array
    {
        return ['draft', 'review', 'approved', 'in_progress', 'completed', 'archived'];
    }

    /**
     * @return array<string>
     */
    abstract protected function validateContent(): array;
}
