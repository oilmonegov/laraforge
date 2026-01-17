<?php

declare(strict_types=1);

use LaraForge\Generators\FeatureTestGenerator;
use LaraForge\LaraForge;

describe('FeatureTestGenerator', function () {
    it('has correct identifier', function () {
        $generator = new FeatureTestGenerator(laraforge());

        expect($generator->identifier())->toBe('feature-test');
    });

    it('has name and description', function () {
        $generator = new FeatureTestGenerator(laraforge());

        expect($generator->name())->toBe('Feature Test');
        expect($generator->description())->toContain('feature tests');
    });

    it('defines options with defaults', function () {
        $generator = new FeatureTestGenerator(laraforge());
        $options = $generator->options();

        expect($options)->toHaveKey('feature');
        expect($options)->toHaveKey('criteria_file');
        expect($options)->toHaveKey('test_type');
        expect($options)->toHaveKey('http_methods');
        expect($options)->toHaveKey('resource');

        expect($options['feature']['required'])->toBeTrue();
        expect($options['test_type']['default'])->toBe('feature');
        expect($options['http_methods']['default'])->toBe([]);
    });

    it('generates empty feature test', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'UserLogin',
        ]);

        expect($files)->toHaveCount(1);
        expect($files[0])->toContain('tests/Feature/UserLoginTest.php');

        $content = file_get_contents($files[0]);
        expect($content)->toContain("describe('UserLogin'");
    });

    it('generates unit test when test_type is unit', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'PasswordValidator',
            'test_type' => 'unit',
        ]);

        expect($files[0])->toContain('tests/Unit/PasswordValidatorTest.php');
    });

    it('generates test from criteria file', function () {
        $tempDir = createTempDirectory();

        // Create criteria file
        $criteriaDir = $tempDir.'/.laraforge/criteria';
        mkdir($criteriaDir, 0755, true);
        file_put_contents($criteriaDir.'/user-registration.yaml', <<<'YAML'
feature: "User Registration"
criteria:
  - id: "AC-001"
    description: "User can register with valid email"
    assertions:
      - "email is validated"
      - "user is created"
  - id: "AC-002"
    description: "User receives confirmation email"
    assertions:
      - "email is sent"
YAML);

        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'UserRegistration',
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("describe('User Registration'");
        expect($content)->toContain('AC-001');
        expect($content)->toContain('AC-002');
        expect($content)->toContain('user can register with valid email');
        expect($content)->toContain('user receives confirmation email');
    });

    it('generates test from specified criteria path', function () {
        $tempDir = createTempDirectory();

        // Create criteria file in custom location
        $customDir = $tempDir.'/custom';
        mkdir($customDir, 0755, true);
        file_put_contents($customDir.'/login.yaml', <<<'YAML'
feature: "Login Feature"
criteria:
  - id: "LOGIN-001"
    description: "User can login with valid credentials"
    assertions:
      - "session is created"
YAML);

        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'Login',
            'criteria_file' => 'custom/login.yaml',
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("describe('Login Feature'");
        expect($content)->toContain('LOGIN-001');
    });

    it('generates HTTP endpoint tests with methods', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'UserApi',
            'http_methods' => ['get', 'post', 'delete'],
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('GET /api/user-api');
        expect($content)->toContain('POST /api/user-api');
        expect($content)->toContain('DELETE /api/user-api');
        expect($content)->toContain('returns successful response');
        expect($content)->toContain('requires authentication');
    });

    it('generates HTTP tests with resource name', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'ProductApi',
            'resource' => 'products',
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('/api/products');
    });

    it('infers HTTP method from criteria description', function () {
        $tempDir = createTempDirectory();

        $criteriaDir = $tempDir.'/.laraforge/criteria';
        mkdir($criteriaDir, 0755, true);
        file_put_contents($criteriaDir.'/user-management.yaml', <<<'YAML'
feature: "User Management"
criteria:
  - id: "AC-001"
    description: "Admin can create new user"
    assertions: []
  - id: "AC-002"
    description: "Admin can view user list"
    assertions: []
  - id: "AC-003"
    description: "Admin can delete user"
    assertions: []
YAML);

        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'UserManagement',
        ]);

        $content = file_get_contents($files[0]);
        // Create should infer POST
        expect($content)->toContain('post(');
        // View should infer GET
        expect($content)->toContain('get(');
        // Delete should infer DELETE
        expect($content)->toContain('delete(');
    });

    it('includes validation tests for POST/PUT endpoints', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'ArticleApi',
            'http_methods' => ['post', 'put'],
        ]);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('validates request data');
        expect($content)->toContain('assertStatus(422)');
    });

    it('skips validation tests for GET/DELETE endpoints', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'ArticleApi',
            'http_methods' => ['get', 'delete'],
        ]);

        $content = file_get_contents($files[0]);
        // GET and DELETE don't have validation
        expect(substr_count($content, 'validates request data'))->toBe(0);
    });

    it('uses correct success status codes', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'ResourceApi',
            'http_methods' => ['get', 'post', 'delete'],
        ]);

        $content = file_get_contents($files[0]);
        // POST should expect 201
        expect($content)->toContain('assertStatus(201)');
        // DELETE should expect 204
        expect($content)->toContain('assertStatus(204)');
        // GET should expect 200
        expect($content)->toContain('assertStatus(200)');
    });

    it('converts feature name to StudlyCase', function () {
        $tempDir = createTempDirectory();
        $laraforge = new LaraForge($tempDir);
        $generator = new FeatureTestGenerator($laraforge);

        $files = $generator->generate([
            'feature' => 'user-profile-update',
        ]);

        expect($files[0])->toContain('UserProfileUpdateTest.php');
    });
});
