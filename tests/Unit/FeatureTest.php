<?php

declare(strict_types=1);

use LaraForge\Project\Feature;

describe('Feature', function () {
    it('creates a feature with factory method', function () {
        $feature = Feature::create('User Authentication', 'Implement auth', 1);

        expect($feature->title())->toBe('User Authentication')
            ->and($feature->description())->toBe('Implement auth')
            ->and($feature->priority())->toBe(1)
            ->and($feature->status())->toBe('planning')
            ->and($feature->phase())->toBe('new')
            ->and($feature->progress())->toBe(0)
            ->and($feature->id())->toContain('user-authentication');
    });

    it('manages status', function () {
        $feature = Feature::create('Test');

        expect($feature->status())->toBe('planning');

        $feature->setStatus('in_progress');

        expect($feature->status())->toBe('in_progress');
    });

    it('manages phase and updates progress', function () {
        $feature = Feature::create('Test');

        expect($feature->phase())->toBe('new')
            ->and($feature->progress())->toBe(0);

        $feature->setPhase('requirements');

        expect($feature->phase())->toBe('requirements')
            ->and($feature->progress())->toBe(15);

        $feature->setPhase('implementation');

        expect($feature->phase())->toBe('implementation')
            ->and($feature->progress())->toBe(50);

        // Progress should not decrease
        $feature->setPhase('design');

        expect($feature->phase())->toBe('design')
            ->and($feature->progress())->toBe(50); // Still 50, not 30
    });

    it('manages branch', function () {
        $feature = Feature::create('Test');

        expect($feature->branch())->toBeNull();

        $feature->setBranch('feature/test-branch');

        expect($feature->branch())->toBe('feature/test-branch');
    });

    it('manages assignee', function () {
        $feature = Feature::create('Test');

        expect($feature->assignee())->toBeNull();

        $feature->setAssignee('developer-1');

        expect($feature->assignee())->toBe('developer-1');
    });

    it('manages progress with bounds', function () {
        $feature = Feature::create('Test');

        $feature->setProgress(50);

        expect($feature->progress())->toBe(50);

        $feature->setProgress(150); // Over 100

        expect($feature->progress())->toBe(100);

        $feature->setProgress(-10); // Negative

        expect($feature->progress())->toBe(0);
    });

    it('manages priority with bounds', function () {
        $feature = Feature::create('Test', '', 3);

        expect($feature->priority())->toBe(3);

        $feature->setPriority(1);

        expect($feature->priority())->toBe(1);

        $feature->setPriority(10); // Over 5

        expect($feature->priority())->toBe(5);

        $feature->setPriority(0); // Under 1

        expect($feature->priority())->toBe(1);
    });

    it('manages documents', function () {
        $feature = Feature::create('Test');

        expect($feature->documents())->toBe([])
            ->and($feature->document('prd'))->toBeNull();

        $feature->addDocument('prd', '/path/to/prd.md');
        $feature->addDocument('frd', '/path/to/frd');

        expect($feature->documents())->toHaveKey('prd')
            ->and($feature->documents())->toHaveKey('frd')
            ->and($feature->document('prd'))->toBe('/path/to/prd.md');
    });

    it('manages tags', function () {
        $feature = Feature::create('Test');

        expect($feature->tags())->toBe([])
            ->and($feature->hasTag('urgent'))->toBeFalse();

        $feature->addTag('urgent');
        $feature->addTag('auth');
        $feature->addTag('urgent'); // Duplicate

        expect($feature->tags())->toHaveCount(2)
            ->and($feature->hasTag('urgent'))->toBeTrue()
            ->and($feature->hasTag('auth'))->toBeTrue();

        $feature->removeTag('urgent');

        expect($feature->hasTag('urgent'))->toBeFalse()
            ->and($feature->tags())->toHaveCount(1);
    });

    it('manages metadata', function () {
        $feature = Feature::create('Test');

        expect($feature->getMetadata('key'))->toBeNull()
            ->and($feature->getMetadata('key', 'default'))->toBe('default');

        $feature->setMetadata('key', 'value');

        expect($feature->getMetadata('key'))->toBe('value')
            ->and($feature->metadata())->toHaveKey('key');
    });

    it('manages title and description', function () {
        $feature = Feature::create('Original Title', 'Original description');

        $feature->setTitle('Updated Title');
        $feature->setDescription('Updated description');

        expect($feature->title())->toBe('Updated Title')
            ->and($feature->description())->toBe('Updated description');
    });

    it('tracks creation and update times', function () {
        $feature = Feature::create('Test');
        $createdAt = $feature->createdAt();
        $updatedAt = $feature->updatedAt();

        usleep(10000); // 10ms delay

        $feature->setTitle('Updated');

        expect($feature->createdAt())->toBe($createdAt)
            ->and($feature->updatedAt())->not->toBe($updatedAt);
    });

    it('converts to array', function () {
        $feature = Feature::create('Test', 'Description', 2);
        $feature->addTag('important');
        $feature->addDocument('prd', '/path/prd.md');

        $array = $feature->toArray();

        expect($array)->toHaveKey('id')
            ->and($array)->toHaveKey('title')
            ->and($array['title'])->toBe('Test')
            ->and($array)->toHaveKey('description')
            ->and($array)->toHaveKey('status')
            ->and($array)->toHaveKey('phase')
            ->and($array)->toHaveKey('branch')
            ->and($array)->toHaveKey('assignee')
            ->and($array)->toHaveKey('progress')
            ->and($array)->toHaveKey('documents')
            ->and($array['documents'])->toHaveKey('prd')
            ->and($array)->toHaveKey('priority')
            ->and($array['priority'])->toBe(2)
            ->and($array)->toHaveKey('tags')
            ->and($array['tags'])->toContain('important')
            ->and($array)->toHaveKey('created_at')
            ->and($array)->toHaveKey('updated_at');
    });

    it('creates from array', function () {
        $data = [
            'id' => 'test-feature-123',
            'title' => 'Test Feature',
            'description' => 'Test description',
            'status' => 'in_progress',
            'phase' => 'implementation',
            'branch' => 'feature/test',
            'assignee' => 'dev-1',
            'progress' => 60,
            'documents' => ['prd' => '/path/prd.md'],
            'priority' => 1,
            'tags' => ['urgent', 'auth'],
            'metadata' => ['custom' => 'data'],
            'created_at' => '2024-01-01T00:00:00+00:00',
            'updated_at' => '2024-01-02T00:00:00+00:00',
        ];

        $feature = Feature::fromArray($data);

        expect($feature->id())->toBe('test-feature-123')
            ->and($feature->title())->toBe('Test Feature')
            ->and($feature->description())->toBe('Test description')
            ->and($feature->status())->toBe('in_progress')
            ->and($feature->phase())->toBe('implementation')
            ->and($feature->branch())->toBe('feature/test')
            ->and($feature->assignee())->toBe('dev-1')
            ->and($feature->progress())->toBe(60)
            ->and($feature->document('prd'))->toBe('/path/prd.md')
            ->and($feature->priority())->toBe(1)
            ->and($feature->hasTag('urgent'))->toBeTrue()
            ->and($feature->getMetadata('custom'))->toBe('data');
    });

    it('round-trips through array conversion', function () {
        $original = Feature::create('Test', 'Description', 2);
        $original->setStatus('in_progress');
        $original->setPhase('implementation');
        $original->setBranch('feature/test');
        $original->setAssignee('dev-1');
        $original->addDocument('prd', '/path/prd.md');
        $original->addTag('important');
        $original->setMetadata('key', 'value');

        $array = $original->toArray();
        $restored = Feature::fromArray($array);

        expect($restored->id())->toBe($original->id())
            ->and($restored->title())->toBe($original->title())
            ->and($restored->status())->toBe($original->status())
            ->and($restored->phase())->toBe($original->phase())
            ->and($restored->branch())->toBe($original->branch())
            ->and($restored->assignee())->toBe($original->assignee())
            ->and($restored->progress())->toBe($original->progress())
            ->and($restored->document('prd'))->toBe($original->document('prd'))
            ->and($restored->priority())->toBe($original->priority())
            ->and($restored->hasTag('important'))->toBeTrue()
            ->and($restored->getMetadata('key'))->toBe('value');
    });
});
