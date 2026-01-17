<?php

declare(strict_types=1);

use LaraForge\Generators\PolicyGenerator;
use LaraForge\LaraForge;

describe('PolicyGenerator', function () {
    it('has correct identifier', function () {
        $generator = new PolicyGenerator(laraforge());

        expect($generator->identifier())->toBe('policy');
    });

    it('has name and description', function () {
        $generator = new PolicyGenerator(laraforge());

        expect($generator->name())->toBe('Policy');
        expect($generator->description())->toContain('Policy');
    });

    it('supports TDD mode', function () {
        $generator = new PolicyGenerator(laraforge());

        expect($generator->supportsTdd())->toBeTrue();
    });

    it('defines standard abilities', function () {
        expect(PolicyGenerator::STANDARD_ABILITIES)->toHaveKeys([
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'forceDelete',
        ]);
    });

    it('defines default abilities', function () {
        expect(PolicyGenerator::DEFAULT_ABILITIES)->toBe([
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
        ]);
    });

    it('defines options with defaults', function () {
        $generator = new PolicyGenerator(laraforge());
        $options = $generator->options();

        expect($options)->toHaveKey('model');
        expect($options)->toHaveKey('abilities');
        expect($options)->toHaveKey('user_model');
        expect($options)->toHaveKey('style');

        expect($options['model']['required'])->toBeTrue();
        expect($options['abilities']['default'])->toBe(PolicyGenerator::DEFAULT_ABILITIES);
        expect($options['user_model']['default'])->toBe('User');
        expect($options['style']['default'])->toBe('regular');
    });

    it('generates policy class with default abilities', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new PolicyGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Post',
        ]);

        expect($files)->toHaveCount(1);
        expect($files[0])->toContain('app/Policies/PostPolicy.php');

        $content = file_get_contents($files[0]);
        expect($content)->toContain('namespace App\Policies;');
        expect($content)->toContain('class PostPolicy');
        expect($content)->toContain('use App\Models\Post;');
        expect($content)->toContain('use App\Models\User;');

        // Default abilities
        expect($content)->toContain('public function viewAny(User $user): bool');
        expect($content)->toContain('public function view(User $user, Post $post): bool');
        expect($content)->toContain('public function create(User $user): bool');
        expect($content)->toContain('public function update(User $user, Post $post): bool');
        expect($content)->toContain('public function delete(User $user, Post $post): bool');
    });

    it('generates policy with custom abilities', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new PolicyGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Article',
            'abilities' => ['view', 'create', 'publish'],
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('public function view(');
        expect($content)->toContain('public function create(');
        expect($content)->toContain('public function publish(');

        // Should not have default abilities that weren't specified
        expect($content)->not->toContain('public function viewAny(');
        expect($content)->not->toContain('public function delete(');
    });

    it('generates policy with custom user model', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new PolicyGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Document',
            'user_model' => 'Admin',
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('use App\Models\Admin;');
        expect($content)->toContain('public function viewAny(Admin $user): bool');
        expect($content)->toContain('public function view(Admin $user, Document $document): bool');
    });

    it('handles abilities without model parameter', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new PolicyGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Comment',
            'abilities' => ['viewAny', 'create'],
        ]);

        $content = file_get_contents($files[0]);

        // viewAny and create don't take the model parameter
        expect($content)->toContain('public function viewAny(User $user): bool');
        expect($content)->toContain('public function create(User $user): bool');

        // Should not have the model parameter for these
        expect($content)->not->toContain('viewAny(User $user, Comment $comment)');
        expect($content)->not->toContain('create(User $user, Comment $comment)');
    });

    it('generates tests in TDD mode', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new PolicyGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Post',
            'style' => 'tdd',
        ]);

        expect($files)->toHaveCount(2);

        $fileNames = array_map(fn ($f) => basename($f), $files);
        expect($fileNames)->toContain('PostPolicyTest.php');
        expect($fileNames)->toContain('PostPolicy.php');

        // Test file should come first in TDD mode
        expect($files[0])->toContain('Test.php');
    });

    it('generates test with ability tests', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new PolicyGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Article',
            'abilities' => ['view', 'update'],
            'style' => 'tdd',
        ]);

        $testFile = $files[0];
        $content = file_get_contents($testFile);

        expect($content)->toContain('use App\Models\Article;');
        expect($content)->toContain('use App\Policies\ArticlePolicy;');
        expect($content)->toContain("describe('view'");
        expect($content)->toContain("describe('update'");
        expect($content)->toContain('allows authorized user');
        expect($content)->toContain('denies unauthorized user');
    });

    it('converts model name to StudlyCase', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new PolicyGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'blog-post',
        ]);

        expect($files[0])->toContain('BlogPostPolicy.php');

        $content = file_get_contents($files[0]);
        expect($content)->toContain('class BlogPostPolicy');
        expect($content)->toContain('BlogPost $blogPost');
    });

    it('includes all soft delete abilities when requested', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new PolicyGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Post',
            'abilities' => ['delete', 'restore', 'forceDelete'],
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('public function delete(User $user, Post $post): bool');
        expect($content)->toContain('public function restore(User $user, Post $post): bool');
        expect($content)->toContain('public function forceDelete(User $user, Post $post): bool');
    });
});
