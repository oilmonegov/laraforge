<?php

declare(strict_types=1);

use LaraForge\Generators\GitHooksGenerator;
use LaraForge\LaraForge;

describe('GitHooksGenerator', function () {
    it('has correct identifier', function () {
        $generator = new GitHooksGenerator(laraforge());

        expect($generator->identifier())->toBe('git-hooks');
    });

    it('has name and description', function () {
        $generator = new GitHooksGenerator(laraforge());

        expect($generator->name())->toBe('Git Hooks');
        expect($generator->description())->toContain('git hooks');
    });

    it('defines available hooks', function () {
        expect(GitHooksGenerator::AVAILABLE_HOOKS)->toHaveKeys([
            'pre-commit',
            'commit-msg',
            'pre-push',
            'post-merge',
            'post-checkout',
        ]);
    });

    it('defines options with defaults', function () {
        $generator = new GitHooksGenerator(laraforge());
        $options = $generator->options();

        expect($options)->toHaveKey('hooks');
        expect($options)->toHaveKey('directory');
        expect($options)->toHaveKey('configure_git');

        expect($options['hooks']['default'])->toBe(['pre-commit', 'commit-msg']);
        expect($options['directory']['default'])->toBe('.githooks');
        expect($options['configure_git']['default'])->toBeTrue();
    });

    it('generates pre-commit and commit-msg hooks by default', function () {
        $tempDir = createTempDirectory();
        mkdir($tempDir.'/.git', 0755, true);

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $files = $generator->generate([]);

        expect($files)->toHaveCount(3); // pre-commit, commit-msg, setup.sh

        $hookNames = array_map(fn ($f) => basename($f), $files);
        expect($hookNames)->toContain('pre-commit');
        expect($hookNames)->toContain('commit-msg');
        expect($hookNames)->toContain('setup.sh');
    });

    it('creates hooks in specified directory', function () {
        $tempDir = createTempDirectory();
        mkdir($tempDir.'/.git', 0755, true);

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $files = $generator->generate([
            'hooks' => ['pre-commit'],
            'directory' => '.custom-hooks',
        ]);

        expect($files[0])->toContain('.custom-hooks/pre-commit');
    });

    it('makes hooks executable', function () {
        $tempDir = createTempDirectory();
        mkdir($tempDir.'/.git', 0755, true);

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $files = $generator->generate([
            'hooks' => ['pre-commit'],
        ]);

        $hookFile = $files[0];
        $perms = fileperms($hookFile);

        // Check if executable (owner execute bit)
        expect($perms & 0100)->toBe(0100);
    });

    it('generates valid bash scripts', function () {
        $tempDir = createTempDirectory();
        mkdir($tempDir.'/.git', 0755, true);

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $files = $generator->generate([
            'hooks' => ['pre-commit', 'commit-msg'],
        ]);

        foreach ($files as $file) {
            if (basename($file) === 'setup.sh') {
                continue;
            }

            $content = file_get_contents($file);
            expect($content)->toStartWith('#!/bin/bash');
        }
    });

    it('generates setup script', function () {
        $tempDir = createTempDirectory();
        mkdir($tempDir.'/.git', 0755, true);

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $files = $generator->generate([]);

        $setupFile = array_filter($files, fn ($f) => str_ends_with($f, 'setup.sh'));
        expect($setupFile)->not->toBeEmpty();

        $content = file_get_contents(array_values($setupFile)[0]);
        expect($content)->toContain('git config core.hooksPath');
    });

    it('skips unknown hooks', function () {
        $tempDir = createTempDirectory();
        mkdir($tempDir.'/.git', 0755, true);

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $files = $generator->generate([
            'hooks' => ['pre-commit', 'unknown-hook'],
        ]);

        $hookNames = array_map(fn ($f) => basename($f), $files);
        expect($hookNames)->not->toContain('unknown-hook');
    });

    it('generates all available hooks when requested', function () {
        $tempDir = createTempDirectory();
        mkdir($tempDir.'/.git', 0755, true);

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $files = $generator->generate([
            'hooks' => array_keys(GitHooksGenerator::AVAILABLE_HOOKS),
        ]);

        // 5 hooks + setup.sh
        expect($files)->toHaveCount(6);
    });

    it('configures git hooks path when requested', function () {
        $tempDir = createTempDirectory();

        // Initialize a proper git repo
        shell_exec("cd {$tempDir} && git init --quiet");

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $generator->generate([
            'hooks' => ['pre-commit'],
            'directory' => '.githooks',
            'configure_git' => true,
        ]);

        // Check git config
        $output = shell_exec("cd {$tempDir} && git config core.hooksPath");
        expect(trim($output ?? ''))->toBe('.githooks');
    });

    it('does not configure git when disabled', function () {
        $tempDir = createTempDirectory();

        // Initialize a proper git repo
        shell_exec("cd {$tempDir} && git init --quiet");

        // Set a different hooks path first
        shell_exec("cd {$tempDir} && git config core.hooksPath .other-hooks");

        $laraforge = new LaraForge($tempDir);
        $generator = new GitHooksGenerator($laraforge);

        $generator->generate([
            'hooks' => ['pre-commit'],
            'configure_git' => false,
        ]);

        // Check git config is unchanged
        $output = shell_exec("cd {$tempDir} && git config core.hooksPath");
        expect(trim($output ?? ''))->toBe('.other-hooks');
    });
});
