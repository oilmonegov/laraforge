<?php

declare(strict_types=1);

use LaraForge\Project\Feature;
use LaraForge\Project\ProjectState;
use Symfony\Component\Filesystem\Filesystem;

describe('ProjectState', function () {
    beforeEach(function () {
        $this->tempDir = createTempDirectory();
        $this->filesystem = new Filesystem;
    });

    afterEach(function () {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    });

    it('initializes a new project', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test Project', '1.0.0');

        expect($state->name())->toBe('Test Project')
            ->and($state->version())->toBe('1.0.0')
            ->and($state->rootPath())->toBe($this->tempDir)
            ->and($this->filesystem->exists($this->tempDir.'/.laraforge'))->toBeTrue()
            ->and($this->filesystem->exists($this->tempDir.'/.laraforge/project.yaml'))->toBeTrue()
            ->and($this->filesystem->exists($this->tempDir.'/.laraforge/docs'))->toBeTrue();
    });

    it('loads existing project state', function () {
        ProjectState::initialize($this->tempDir, 'Test Project', '2.0.0');

        $loaded = ProjectState::load($this->tempDir);

        expect($loaded)->not->toBeNull()
            ->and($loaded->name())->toBe('Test Project')
            ->and($loaded->version())->toBe('2.0.0');
    });

    it('returns null when no project exists', function () {
        $loaded = ProjectState::load($this->tempDir);

        expect($loaded)->toBeNull();
    });

    it('manages features', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $feature = Feature::create('User Auth', 'Implement authentication');

        $state->addFeature($feature);

        expect($state->features())->toHaveCount(1)
            ->and($state->feature($feature->id()))->not->toBeNull()
            ->and($state->feature($feature->id())->title())->toBe('User Auth');
    });

    it('manages current feature', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $feature1 = Feature::create('Feature 1');
        $feature2 = Feature::create('Feature 2');

        $state->addFeature($feature1);
        $state->addFeature($feature2);

        expect($state->currentFeature())->toBeNull();

        $state->setCurrentFeature($feature1->id());

        expect($state->currentFeature())->not->toBeNull()
            ->and($state->currentFeature()->id())->toBe($feature1->id());
    });

    it('updates features', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $feature = Feature::create('Original');

        $state->addFeature($feature);

        $feature->setTitle('Updated');
        $state->updateFeature($feature);

        $reloaded = ProjectState::load($this->tempDir);

        expect($reloaded->feature($feature->id())->title())->toBe('Updated');
    });

    it('removes features', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $feature = Feature::create('To Remove');

        $state->addFeature($feature);
        $state->setCurrentFeature($feature->id());

        expect($state->features())->toHaveCount(1)
            ->and($state->currentFeature())->not->toBeNull();

        $state->removeFeature($feature->id());

        expect($state->features())->toHaveCount(0)
            ->and($state->currentFeature())->toBeNull();
    });

    it('filters features by status', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');

        $feature1 = Feature::create('Feature 1');
        $feature1->setStatus('in_progress');

        $feature2 = Feature::create('Feature 2');
        $feature2->setStatus('completed');

        $feature3 = Feature::create('Feature 3');
        $feature3->setStatus('in_progress');

        $state->addFeature($feature1);
        $state->addFeature($feature2);
        $state->addFeature($feature3);

        $inProgress = $state->featuresByStatus('in_progress');
        $completed = $state->featuresByStatus('completed');

        expect($inProgress)->toHaveCount(2)
            ->and($completed)->toHaveCount(1);
    });

    it('manages backlog', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');

        expect($state->backlog())->toBe([]);

        $state->addToBacklog('FEAT-001', 'New feature idea', 2);
        $state->addToBacklog('FEAT-002', 'Another feature', 1);

        expect($state->backlog())->toHaveCount(2)
            ->and($state->backlog()[0]['id'])->toBe('FEAT-001')
            ->and($state->backlog()[0]['priority'])->toBe(2);

        $state->removeFromBacklog('FEAT-001');

        expect($state->backlog())->toHaveCount(1)
            ->and($state->backlog()[0]['id'])->toBe('FEAT-002');
    });

    it('promotes backlog item to feature', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $state->addToBacklog('FEAT-001', 'Backlog Feature', 1);

        expect($state->backlog())->toHaveCount(1)
            ->and($state->features())->toHaveCount(0);

        $feature = $state->promoteFromBacklog('FEAT-001');

        expect($feature)->not->toBeNull()
            ->and($feature->title())->toBe('Backlog Feature')
            ->and($feature->priority())->toBe(1)
            ->and($state->backlog())->toHaveCount(0)
            ->and($state->features())->toHaveCount(1);
    });

    it('returns null when promoting non-existent backlog item', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');

        $feature = $state->promoteFromBacklog('NON-EXISTENT');

        expect($feature)->toBeNull();
    });

    it('manages config with dot notation', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');

        expect($state->config())->toBe([])
            ->and($state->getConfig('workflow.default'))->toBeNull()
            ->and($state->getConfig('workflow.default', 'feature'))->toBe('feature');

        $state->setConfig('workflow.default', 'feature');
        $state->setConfig('workflow.parallel', true);
        $state->setConfig('deep.nested.value', 'test');

        expect($state->getConfig('workflow.default'))->toBe('feature')
            ->and($state->getConfig('workflow.parallel'))->toBeTrue()
            ->and($state->getConfig('deep.nested.value'))->toBe('test');
    });

    it('persists state to file', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $feature = Feature::create('Persisted Feature');

        $state->addFeature($feature);
        $state->setCurrentFeature($feature->id());
        $state->setConfig('key', 'value');

        // Load fresh from disk
        $loaded = ProjectState::load($this->tempDir);

        expect($loaded->features())->toHaveCount(1)
            ->and($loaded->currentFeature())->not->toBeNull()
            ->and($loaded->currentFeature()->id())->toBe($feature->id())
            ->and($loaded->getConfig('key'))->toBe('value');
    });

    it('converts to array', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test', '1.0.0');
        $feature = Feature::create('Test Feature');

        $state->addFeature($feature);
        $state->addToBacklog('FEAT-001', 'Backlog item');
        $state->setConfig('key', 'value');

        $array = $state->toArray();

        expect($array)->toHaveKey('project')
            ->and($array['project']['name'])->toBe('Test')
            ->and($array['project']['version'])->toBe('1.0.0')
            ->and($array)->toHaveKey('features')
            ->and($array['features'])->toHaveCount(1)
            ->and($array)->toHaveKey('backlog')
            ->and($array['backlog'])->toHaveCount(1)
            ->and($array)->toHaveKey('config')
            ->and($array['config']['key'])->toBe('value');
    });

    it('provides state path', function () {
        $state = ProjectState::initialize($this->tempDir, 'Test');

        expect($state->statePath())->toBe($this->tempDir.'/.laraforge/project.yaml');
    });
});
