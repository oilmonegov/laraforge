<?php

declare(strict_types=1);

use LaraForge\LaraForge;

describe('LaraForge', function () {
    it('can be instantiated', function () {
        $laraforge = laraforge();

        expect($laraforge)->toBeInstanceOf(LaraForge::class);
    });

    it('returns the correct version', function () {
        $laraforge = laraforge();

        expect($laraforge->version())->toBe(LaraForge::VERSION);
    });

    it('uses provided working directory', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);

        expect($laraforge->workingDirectory())->toBe($tempDir);
    });

    it('can change working directory', function () {
        $tempDir1 = createTempDirectory();
        $tempDir2 = createTempDirectory();

        $laraforge = new LaraForge($tempDir1);
        $laraforge->setWorkingDirectory($tempDir2);

        expect($laraforge->workingDirectory())->toBe($tempDir2);
    });

    it('returns correct override path', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);

        expect($laraforge->overridePath())->toBe($tempDir . '/.laraforge');
    });

    it('detects when overrides exist', function () {
        $tempDir = createTempDirectory();
        mkdir($tempDir . '/.laraforge', 0755, true);

        $laraforge = new LaraForge($tempDir);

        expect($laraforge->hasOverrides())->toBeTrue();
    });

    it('detects when overrides do not exist', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);

        expect($laraforge->hasOverrides())->toBeFalse();
    });

    it('starts with no adapters', function () {
        $laraforge = laraforge();

        expect($laraforge->adapters())->toBeEmpty();
    });

    it('starts with no plugins', function () {
        $laraforge = laraforge();

        expect($laraforge->plugins())->toBeEmpty();
    });

    it('starts with no generators', function () {
        $laraforge = laraforge();

        expect($laraforge->generators())->toBeEmpty();
    });

    it('returns null for non-existent generator', function () {
        $laraforge = laraforge();

        expect($laraforge->generator('non-existent'))->toBeNull();
    });

    it('has a config loader', function () {
        $laraforge = laraforge();

        expect($laraforge->config())
            ->toBeInstanceOf(\LaraForge\Contracts\ConfigLoaderInterface::class);
    });

    it('has a template engine', function () {
        $laraforge = laraforge();

        expect($laraforge->templates())
            ->toBeInstanceOf(\LaraForge\Contracts\TemplateEngineInterface::class);
    });
});
