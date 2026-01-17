<?php

declare(strict_types=1);

use LaraForge\Criteria\AcceptanceCriteria;
use LaraForge\Criteria\AcceptanceCriterion;
use LaraForge\Criteria\CriteriaLoader;
use LaraForge\Exceptions\ConfigurationException;
use Symfony\Component\Yaml\Yaml;

describe('CriteriaLoader', function () {
    it('can load criteria from yaml file', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteriaPath = $tempDir.'/criteria.yaml';
        file_put_contents($criteriaPath, Yaml::dump([
            'feature' => 'User Registration',
            'criteria' => [
                [
                    'id' => 'AC-001',
                    'description' => 'User can register with valid email',
                    'assertions' => ['user is created', 'email is sent'],
                ],
            ],
        ]));

        $criteria = $loader->load($criteriaPath);

        expect($criteria)->toBeInstanceOf(AcceptanceCriteria::class);
        expect($criteria->feature)->toBe('User Registration');
        expect($criteria->count())->toBe(1);
        expect($criteria->get('AC-001'))->toBeInstanceOf(AcceptanceCriterion::class);
        expect($criteria->get('AC-001')->description)->toBe('User can register with valid email');
    });

    it('can load criteria from json file', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteriaPath = $tempDir.'/criteria.json';
        file_put_contents($criteriaPath, json_encode([
            'feature' => 'Login Feature',
            'criteria' => [
                [
                    'id' => 'AC-001',
                    'description' => 'User can login',
                    'assertions' => [],
                ],
            ],
        ]));

        $criteria = $loader->load($criteriaPath);

        expect($criteria->feature)->toBe('Login Feature');
        expect($criteria->count())->toBe(1);
    });

    it('throws exception for missing file', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        expect(fn () => $loader->load('/non/existent/file.yaml'))
            ->toThrow(ConfigurationException::class, 'Criteria file not found');
    });

    it('throws exception for invalid yaml', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteriaPath = $tempDir.'/invalid.yaml';
        file_put_contents($criteriaPath, "feature: 'missing quote\ncriteria:");

        expect(fn () => $loader->load($criteriaPath))
            ->toThrow(ConfigurationException::class);
    });

    it('throws exception for missing feature field', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteriaPath = $tempDir.'/missing-feature.yaml';
        file_put_contents($criteriaPath, Yaml::dump([
            'criteria' => [],
        ]));

        expect(fn () => $loader->load($criteriaPath))
            ->toThrow(ConfigurationException::class, "missing 'feature' field");
    });

    it('throws exception for missing criteria array', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteriaPath = $tempDir.'/missing-criteria.yaml';
        file_put_contents($criteriaPath, Yaml::dump([
            'feature' => 'Test Feature',
        ]));

        expect(fn () => $loader->load($criteriaPath))
            ->toThrow(ConfigurationException::class, "missing 'criteria' array");
    });

    it('can find criteria file by feature name', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteriaDir = $tempDir.'/criteria';
        mkdir($criteriaDir);
        file_put_contents($criteriaDir.'/user-auth.yaml', Yaml::dump([
            'feature' => 'User Auth',
            'criteria' => [],
        ]));

        $found = $loader->find('user-auth', $criteriaDir);

        expect($found)->toBe($criteriaDir.'/user-auth.yaml');
    });

    it('returns null when criteria file not found', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $found = $loader->find('non-existent', $tempDir);

        expect($found)->toBeNull();
    });

    it('can validate test coverage', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteria = AcceptanceCriteria::fromArray([
            'feature' => 'Test Feature',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'First criterion'],
                ['id' => 'AC-002', 'description' => 'Second criterion'],
            ],
        ]);

        $testPath = $tempDir.'/tests';
        mkdir($testPath);
        file_put_contents($testPath.'/FeatureTest.php', <<<'PHP'
<?php
/**
 * Acceptance Criteria: AC-001
 */
it('first criterion', function () {
    // test implementation
});
PHP);

        $result = $loader->validate($criteria, $testPath);

        expect($result->coveredIds)->toBe(['AC-001']);
        expect($result->missingIds)->toBe(['AC-002']);
        expect($result->coveragePercentage())->toBe(50.0);
    });

    it('returns full coverage for empty criteria', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteria = AcceptanceCriteria::fromArray([
            'feature' => 'Empty Feature',
            'criteria' => [],
        ]);

        $result = $loader->validate($criteria, $tempDir);

        expect($result->isFullyCovered())->toBeTrue();
    });

    it('returns no coverage when test path does not exist', function () {
        $tempDir = createTempDirectory();
        $laraforge = laraforge($tempDir);
        $loader = new CriteriaLoader($laraforge);

        $criteria = AcceptanceCriteria::fromArray([
            'feature' => 'Test Feature',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'Criterion'],
            ],
        ]);

        $result = $loader->validate($criteria, $tempDir.'/non-existent');

        expect($result->isFullyCovered())->toBeFalse();
        expect($result->missingIds)->toBe(['AC-001']);
    });
});
