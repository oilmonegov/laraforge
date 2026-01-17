<?php

declare(strict_types=1);

use LaraForge\Exceptions\TemplateException;
use LaraForge\Templates\TemplateEngine;

describe('TemplateEngine', function () {
    it('replaces simple variables', function () {
        $engine = new TemplateEngine;

        $result = $engine->render('Hello {{ name }}!', ['name' => 'World']);

        expect($result)->toBe('Hello World!');
    });

    it('replaces multiple variables', function () {
        $engine = new TemplateEngine;

        $result = $engine->render(
            '{{ greeting }}, {{ name }}!',
            ['greeting' => 'Hello', 'name' => 'World']
        );

        expect($result)->toBe('Hello, World!');
    });

    it('leaves unmatched variables as-is', function () {
        $engine = new TemplateEngine;

        $result = $engine->render('Hello {{ name }}!', []);

        expect($result)->toBe('Hello {{ name }}!');
    });

    it('handles variables with spaces', function () {
        $engine = new TemplateEngine;

        $result = $engine->render('Hello {{  name  }}!', ['name' => 'World']);

        expect($result)->toBe('Hello World!');
    });

    it('handles raw output syntax', function () {
        $engine = new TemplateEngine;

        $result = $engine->render('Hello {!! name !!}!', ['name' => 'World']);

        expect($result)->toBe('Hello World!');
    });

    it('processes if conditionals when true', function () {
        $engine = new TemplateEngine;

        $template = '{{#if showGreeting}}Hello{{/if}}';
        $result = $engine->render($template, ['showGreeting' => true]);

        expect($result)->toBe('Hello');
    });

    it('removes if conditionals when false', function () {
        $engine = new TemplateEngine;

        $template = '{{#if showGreeting}}Hello{{/if}}';
        $result = $engine->render($template, ['showGreeting' => false]);

        expect($result)->toBe('');
    });

    it('processes each loops', function () {
        $engine = new TemplateEngine;

        $template = '{{#each items}}{{ this }},{{/each}}';
        $result = $engine->render($template, ['items' => ['a', 'b', 'c']]);

        expect($result)->toBe('a,b,c,');
    });

    it('provides loop index in each', function () {
        $engine = new TemplateEngine;

        $template = '{{#each items}}{{ @index }}{{/each}}';
        $result = $engine->render($template, ['items' => ['a', 'b', 'c']]);

        expect($result)->toBe('012');
    });

    it('can render from file', function () {
        $tempDir = createTempDirectory();
        $templatePath = $tempDir.'/template.txt';
        file_put_contents($templatePath, 'Hello {{ name }}!');

        $engine = new TemplateEngine;
        $engine->addPath($tempDir);

        $result = $engine->renderFile('template.txt', ['name' => 'World']);

        expect($result)->toBe('Hello World!');
    });

    it('throws exception for non-existent template', function () {
        $engine = new TemplateEngine;

        expect(fn () => $engine->renderFile('non-existent.txt'))
            ->toThrow(TemplateException::class);
    });

    it('resolves templates with priority', function () {
        $tempDir1 = createTempDirectory();
        $tempDir2 = createTempDirectory();

        file_put_contents($tempDir1.'/template.txt', 'Low priority');
        file_put_contents($tempDir2.'/template.txt', 'High priority');

        $engine = new TemplateEngine;
        $engine->addPath($tempDir1, 10);
        $engine->addPath($tempDir2, 100);

        $result = $engine->renderFile('template.txt');

        expect($result)->toBe('High priority');
    });

    it('checks if template exists', function () {
        $tempDir = createTempDirectory();
        file_put_contents($tempDir.'/exists.txt', 'content');

        $engine = new TemplateEngine;
        $engine->addPath($tempDir);

        expect($engine->exists('exists.txt'))->toBeTrue();
        expect($engine->exists('not-exists.txt'))->toBeFalse();
    });

    it('resolves correct path for template', function () {
        $tempDir = createTempDirectory();
        file_put_contents($tempDir.'/template.txt', 'content');

        $engine = new TemplateEngine;
        $engine->addPath($tempDir);

        expect($engine->resolve('template.txt'))->toBe($tempDir.'/template.txt');
        expect($engine->resolve('not-exists.txt'))->toBeNull();
    });
});
