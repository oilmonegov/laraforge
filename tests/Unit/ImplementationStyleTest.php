<?php

declare(strict_types=1);

use LaraForge\Enums\ImplementationStyle;

describe('ImplementationStyle', function () {
    it('has regular style', function () {
        $style = ImplementationStyle::Regular;

        expect($style->value)->toBe('regular');
        expect($style->label())->toBe('Regular');
        expect($style->description())->toBe('Generate implementation code directly');
    });

    it('has tdd style', function () {
        $style = ImplementationStyle::TDD;

        expect($style->value)->toBe('tdd');
        expect($style->label())->toBe('Test-Driven Development (TDD)');
        expect($style->description())->toBe('Generate test files first, then implementation stubs');
    });

    it('can be created from string', function () {
        expect(ImplementationStyle::fromString('regular'))->toBe(ImplementationStyle::Regular);
        expect(ImplementationStyle::fromString('tdd'))->toBe(ImplementationStyle::TDD);
        expect(ImplementationStyle::fromString('TDD'))->toBe(ImplementationStyle::TDD);
        expect(ImplementationStyle::fromString('REGULAR'))->toBe(ImplementationStyle::Regular);
    });

    it('defaults to regular for unknown values', function () {
        expect(ImplementationStyle::fromString('unknown'))->toBe(ImplementationStyle::Regular);
        expect(ImplementationStyle::fromString(''))->toBe(ImplementationStyle::Regular);
    });
});

describe('AcceptanceCriterion', function () {
    it('can be created from array', function () {
        $criterion = \LaraForge\Criteria\AcceptanceCriterion::fromArray([
            'id' => 'AC-001',
            'description' => 'User can register',
            'assertions' => ['user is created', 'email is sent'],
        ]);

        expect($criterion->id)->toBe('AC-001');
        expect($criterion->description)->toBe('User can register');
        expect($criterion->assertions)->toBe(['user is created', 'email is sent']);
    });

    it('defaults assertions to empty array', function () {
        $criterion = \LaraForge\Criteria\AcceptanceCriterion::fromArray([
            'id' => 'AC-001',
            'description' => 'Test',
        ]);

        expect($criterion->assertions)->toBe([]);
    });

    it('can convert to array', function () {
        $criterion = new \LaraForge\Criteria\AcceptanceCriterion(
            id: 'AC-001',
            description: 'Test description',
            assertions: ['assert 1'],
        );

        expect($criterion->toArray())->toBe([
            'id' => 'AC-001',
            'description' => 'Test description',
            'assertions' => ['assert 1'],
        ]);
    });

    it('generates test method name', function () {
        $criterion = new \LaraForge\Criteria\AcceptanceCriterion(
            id: 'AC-001',
            description: 'User can register with valid email',
        );

        expect($criterion->toTestMethodName())->toBe('user_can_register_with_valid_email');
    });

    it('generates test label', function () {
        $criterion = new \LaraForge\Criteria\AcceptanceCriterion(
            id: 'AC-001',
            description: 'User Can Register With Valid Email',
        );

        expect($criterion->toTestLabel())->toBe('user can register with valid email');
    });
});

describe('AcceptanceCriteria', function () {
    it('can be created from array', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'User Registration',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'First'],
                ['id' => 'AC-002', 'description' => 'Second'],
            ],
        ]);

        expect($criteria->feature)->toBe('User Registration');
        expect($criteria->count())->toBe(2);
    });

    it('can check if criterion exists', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'Test'],
            ],
        ]);

        expect($criteria->has('AC-001'))->toBeTrue();
        expect($criteria->has('AC-999'))->toBeFalse();
    });

    it('can get all criterion ids', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'First'],
                ['id' => 'AC-002', 'description' => 'Second'],
            ],
        ]);

        expect($criteria->ids())->toBe(['AC-001', 'AC-002']);
    });

    it('is iterable', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'First'],
                ['id' => 'AC-002', 'description' => 'Second'],
            ],
        ]);

        $ids = [];
        foreach ($criteria as $criterion) {
            $ids[] = $criterion->id;
        }

        expect($ids)->toBe(['AC-001', 'AC-002']);
    });

    it('can add criteria', function () {
        $criteria = new \LaraForge\Criteria\AcceptanceCriteria('Test');

        expect($criteria->isEmpty())->toBeTrue();

        $criteria->add(new \LaraForge\Criteria\AcceptanceCriterion('AC-001', 'Test'));

        expect($criteria->isEmpty())->toBeFalse();
        expect($criteria->count())->toBe(1);
    });
});

describe('ValidationResult', function () {
    it('reports full coverage', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'Test'],
            ],
        ]);

        $result = new \LaraForge\Criteria\ValidationResult(
            coveredIds: ['AC-001'],
            missingIds: [],
            criteria: $criteria,
        );

        expect($result->isFullyCovered())->toBeTrue();
        expect($result->coveragePercentage())->toBe(100.0);
    });

    it('reports partial coverage', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'First'],
                ['id' => 'AC-002', 'description' => 'Second'],
            ],
        ]);

        $result = new \LaraForge\Criteria\ValidationResult(
            coveredIds: ['AC-001'],
            missingIds: ['AC-002'],
            criteria: $criteria,
        );

        expect($result->isFullyCovered())->toBeFalse();
        expect($result->coveragePercentage())->toBe(50.0);
    });

    it('can create fully covered result', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'Test'],
            ],
        ]);

        $result = \LaraForge\Criteria\ValidationResult::fullyCovered($criteria);

        expect($result->isFullyCovered())->toBeTrue();
        expect($result->coveredIds)->toBe(['AC-001']);
    });

    it('can create no coverage result', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'Test'],
            ],
        ]);

        $result = \LaraForge\Criteria\ValidationResult::noCoverage($criteria);

        expect($result->isFullyCovered())->toBeFalse();
        expect($result->missingIds)->toBe(['AC-001']);
    });

    it('returns covered criteria', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'First'],
                ['id' => 'AC-002', 'description' => 'Second'],
            ],
        ]);

        $result = new \LaraForge\Criteria\ValidationResult(
            coveredIds: ['AC-001'],
            missingIds: ['AC-002'],
            criteria: $criteria,
        );

        $covered = $result->coveredCriteria();

        expect($covered)->toHaveCount(1);
        expect($covered[0]->id)->toBe('AC-001');
    });

    it('returns missing criteria', function () {
        $criteria = \LaraForge\Criteria\AcceptanceCriteria::fromArray([
            'feature' => 'Test',
            'criteria' => [
                ['id' => 'AC-001', 'description' => 'First'],
                ['id' => 'AC-002', 'description' => 'Second'],
            ],
        ]);

        $result = new \LaraForge\Criteria\ValidationResult(
            coveredIds: ['AC-001'],
            missingIds: ['AC-002'],
            criteria: $criteria,
        );

        $missing = $result->missingCriteria();

        expect($missing)->toHaveCount(1);
        expect($missing[1]->id)->toBe('AC-002');
    });
});
