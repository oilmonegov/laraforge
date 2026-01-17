<?php

declare(strict_types=1);

use LaraForge\Generators\ApiResourceGenerator;
use LaraForge\LaraForge;

describe('ApiResourceGenerator', function () {
    it('has correct identifier', function () {
        $generator = new ApiResourceGenerator(laraforge());

        expect($generator->identifier())->toBe('api-resource');
    });

    it('has name and description', function () {
        $generator = new ApiResourceGenerator(laraforge());

        expect($generator->name())->toBe('API Resource');
        expect($generator->description())->toContain('API Resource');
    });

    it('supports TDD mode', function () {
        $generator = new ApiResourceGenerator(laraforge());

        expect($generator->supportsTdd())->toBeTrue();
    });

    it('defines options with defaults', function () {
        $generator = new ApiResourceGenerator(laraforge());
        $options = $generator->options();

        expect($options)->toHaveKey('model');
        expect($options)->toHaveKey('attributes');
        expect($options)->toHaveKey('include_collection');
        expect($options)->toHaveKey('openapi');
        expect($options)->toHaveKey('style');

        expect($options['model']['required'])->toBeTrue();
        expect($options['attributes']['default'])->toBe([]);
        expect($options['include_collection']['default'])->toBeFalse();
        expect($options['openapi']['default'])->toBeFalse();
        expect($options['style']['default'])->toBe('regular');
    });

    it('generates resource class', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ApiResourceGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'User',
        ]);

        expect($files)->toHaveCount(1);
        expect($files[0])->toContain('app/Http/Resources/UserResource.php');

        $content = file_get_contents($files[0]);
        expect($content)->toContain('class UserResource extends JsonResource');
        expect($content)->toContain('namespace App\Http\Resources;');
    });

    it('generates resource with attributes', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ApiResourceGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'User',
            'attributes' => ['name', 'email'],
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("'name' => \$this->name");
        expect($content)->toContain("'email' => \$this->email");
    });

    it('generates collection class when requested', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ApiResourceGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'User',
            'include_collection' => true,
        ]);

        expect($files)->toHaveCount(2);

        $fileNames = array_map(fn ($f) => basename($f), $files);
        expect($fileNames)->toContain('UserResource.php');
        expect($fileNames)->toContain('UserCollection.php');

        $collectionFile = $files[1];
        $content = file_get_contents($collectionFile);
        expect($content)->toContain('class UserCollection extends ResourceCollection');
        expect($content)->toContain('public $collects = UserResource::class');
    });

    it('includes OpenAPI annotations when requested', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ApiResourceGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Product',
            'attributes' => ['name', 'price'],
            'openapi' => true,
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('@OA\Schema');
        expect($content)->toContain('@OA\Property');
    });

    it('generates tests in TDD mode', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ApiResourceGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'User',
            'style' => 'tdd',
        ]);

        expect($files)->toHaveCount(2);

        $fileNames = array_map(fn ($f) => basename($f), $files);
        expect($fileNames)->toContain('UserResourceTest.php');
        expect($fileNames)->toContain('UserResource.php');

        // Test file should come first in TDD mode
        expect($files[0])->toContain('Test.php');
    });

    it('generates collection test in TDD mode with collection', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ApiResourceGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'Post',
            'include_collection' => true,
            'style' => 'tdd',
        ]);

        expect($files)->toHaveCount(4);

        $fileNames = array_map(fn ($f) => basename($f), $files);
        expect($fileNames)->toContain('PostResourceTest.php');
        expect($fileNames)->toContain('PostCollectionTest.php');
        expect($fileNames)->toContain('PostResource.php');
        expect($fileNames)->toContain('PostCollection.php');
    });

    it('converts model name to StudlyCase', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new ApiResourceGenerator($laraforge);

        $files = $generator->generate([
            'model' => 'user-profile',
        ]);

        expect($files[0])->toContain('UserProfileResource.php');
    });
});
