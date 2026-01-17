<?php

declare(strict_types=1);

namespace LaraForge\Documents;

use LaraForge\Documents\Contracts\DocumentInterface;
use LaraForge\Documents\Contracts\DocumentParserInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class DocumentRegistry
{
    /**
     * @var array<string, DocumentParserInterface>
     */
    private array $parsers = [];

    /**
     * @var array<string, DocumentInterface>
     */
    private array $documents = [];

    private Filesystem $filesystem;

    public function __construct(
        private readonly string $basePath,
    ) {
        $this->filesystem = new Filesystem;
        $this->registerDefaultParsers();
    }

    public function registerParser(DocumentParserInterface $parser): void
    {
        $this->parsers[$parser->type()] = $parser;
    }

    public function getParser(string $type): ?DocumentParserInterface
    {
        return $this->parsers[$type] ?? null;
    }

    public function load(string $path): ?DocumentInterface
    {
        $fullPath = $this->resolvePath($path);

        if (! $this->filesystem->exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $type = $this->detectType($fullPath, $content);
        $parser = $this->parsers[$type] ?? null;

        if (! $parser) {
            return $this->loadGenericDocument($content, $fullPath);
        }

        $document = $parser->parse($content, $fullPath);
        $this->documents[$fullPath] = $document;

        return $document;
    }

    public function save(DocumentInterface $document, ?string $path = null): string
    {
        $savePath = $path ?? $document->path() ?? $this->generatePath($document);
        $fullPath = $this->resolvePath($savePath);

        $directory = dirname($fullPath);
        if (! $this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory, 0755);
        }

        $content = $this->serializeDocument($document);
        $this->filesystem->dumpFile($fullPath, $content);

        if ($document instanceof Document) {
            $document->setPath($fullPath);
        }

        $this->documents[$fullPath] = $document;

        return $fullPath;
    }

    public function get(string $path): ?DocumentInterface
    {
        $fullPath = $this->resolvePath($path);

        return $this->documents[$fullPath] ?? $this->load($path);
    }

    public function has(string $path): bool
    {
        $fullPath = $this->resolvePath($path);

        return isset($this->documents[$fullPath]) || $this->filesystem->exists($fullPath);
    }

    /**
     * @return array<DocumentInterface>
     */
    public function all(): array
    {
        return array_values($this->documents);
    }

    /**
     * @return array<DocumentInterface>
     */
    public function byType(string $type): array
    {
        return array_filter(
            $this->documents,
            fn (DocumentInterface $doc) => $doc->type() === $type
        );
    }

    /**
     * @return array<DocumentInterface>
     */
    public function byFeature(string $featureId): array
    {
        return array_filter(
            $this->documents,
            fn (DocumentInterface $doc) => $doc->featureId() === $featureId
        );
    }

    /**
     * Scan a directory for documents.
     *
     * @return array<DocumentInterface>
     */
    public function scan(?string $directory = null): array
    {
        $dir = $directory ?? $this->basePath;
        $documents = [];

        if (! $this->filesystem->exists($dir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->isDocumentFile($file->getPathname())) {
                $doc = $this->load($file->getPathname());
                if ($doc) {
                    $documents[] = $doc;
                }
            }
        }

        return $documents;
    }

    public function remove(string $path): void
    {
        $fullPath = $this->resolvePath($path);
        unset($this->documents[$fullPath]);
    }

    public function delete(string $path): void
    {
        $fullPath = $this->resolvePath($path);

        if ($this->filesystem->exists($fullPath)) {
            $this->filesystem->remove($fullPath);
        }

        unset($this->documents[$fullPath]);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->basePath.'/'.$path;
    }

    private function detectType(string $path, string $content): string
    {
        $filename = basename($path);

        if (str_contains($filename, '.prd.')) {
            return 'prd';
        }
        if (str_contains($filename, '.frd.')) {
            return 'frd';
        }
        if (str_contains($filename, '.design.')) {
            return 'design';
        }
        if (str_contains($filename, '.test-contract.') || str_contains($filename, '.contract.')) {
            return 'test-contract';
        }

        // Try to detect from content
        try {
            $data = Yaml::parse($content);
            if (is_array($data)) {
                if (isset($data['type'])) {
                    return $data['type'];
                }
                if (isset($data['objectives']) || isset($data['user_stories'])) {
                    return 'prd';
                }
                if (isset($data['stepwise_refinement']) || isset($data['design']['stepwise_refinement'])) {
                    return 'frd';
                }
                if (isset($data['contracts'])) {
                    return 'test-contract';
                }
                if (isset($data['components']) || isset($data['pseudocode'])) {
                    return 'design';
                }
            }
        } catch (\Exception) {
            // Not YAML, try markdown
        }

        return 'generic';
    }

    private function loadGenericDocument(string $content, string $path): DocumentInterface
    {
        try {
            $data = Yaml::parse($content);
            if (is_array($data)) {
                return new class($data, $path) extends Document
                {
                    public function __construct(array $data, string $path)
                    {
                        parent::__construct(
                            title: $data['title'] ?? basename($path),
                            version: $data['version'] ?? '1.0',
                            status: $data['status'] ?? 'draft',
                            featureId: $data['feature_id'] ?? null,
                            content: $data,
                            path: $path,
                        );
                    }

                    public function type(): string
                    {
                        return 'generic';
                    }

                    protected function validateContent(): array
                    {
                        return [];
                    }
                };
            }
        } catch (\Exception) {
            // Fall through to return basic document
        }

        return new class($content, $path) extends Document
        {
            private string $raw;

            public function __construct(string $content, string $path)
            {
                parent::__construct(
                    title: basename($path),
                    path: $path,
                );
                $this->raw = $content;
            }

            public function type(): string
            {
                return 'generic';
            }

            public function rawContent(): string
            {
                return $this->raw;
            }

            protected function validateContent(): array
            {
                return [];
            }
        };
    }

    private function serializeDocument(DocumentInterface $document): string
    {
        return $document->serialize();
    }

    private function generatePath(DocumentInterface $document): string
    {
        $type = $document->type();
        $featureId = $document->featureId() ?? 'general';
        $slug = $this->slugify($document->title());

        return match ($type) {
            'prd' => "prd/{$slug}.prd.yaml",
            'frd' => "frd/{$featureId}.frd.yaml",
            'design' => "design/{$featureId}.design.yaml",
            'test-contract' => "tests/{$featureId}.contract.yaml",
            default => "{$type}/{$slug}.yaml",
        };
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        $text = preg_replace('/\s+/', '-', trim($text ?? ''));

        return strtolower($text ?? '');
    }

    private function isDocumentFile(string $path): bool
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return in_array($ext, ['yaml', 'yml', 'json', 'md'], true);
    }

    private function registerDefaultParsers(): void
    {
        // Default parsers can be registered here
    }
}
