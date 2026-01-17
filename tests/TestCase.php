<?php

declare(strict_types=1);

namespace LaraForge\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class TestCase extends BaseTestCase
{
    protected Filesystem $filesystem;

    protected ?string $tempDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem;
    }

    protected function tearDown(): void
    {
        if ($this->tempDirectory !== null && $this->filesystem->exists($this->tempDirectory)) {
            $this->filesystem->remove($this->tempDirectory);
        }

        parent::tearDown();
    }

    protected function createTempDirectory(): string
    {
        $this->tempDirectory = sys_get_temp_dir().'/laraforge-test-'.uniqid();
        $this->filesystem->mkdir($this->tempDirectory);

        return $this->tempDirectory;
    }

    protected function createFile(string $relativePath, string $content = ''): string
    {
        $path = $this->tempDirectory.'/'.ltrim($relativePath, '/');
        $this->filesystem->mkdir(dirname($path));
        $this->filesystem->dumpFile($path, $content);

        return $path;
    }
}
