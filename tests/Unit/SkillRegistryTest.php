<?php

declare(strict_types=1);

use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillInterface;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillRegistry;
use LaraForge\Skills\SkillResult;

class SkillRegistryTestSkill extends Skill
{
    public function identifier(): string
    {
        return 'test-skill';
    }

    public function name(): string
    {
        return 'Test Skill';
    }

    public function description(): string
    {
        return 'A test skill for testing';
    }

    public function category(): string
    {
        return 'testing';
    }

    public function tags(): array
    {
        return ['test', 'example'];
    }

    public function parameters(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'The name parameter',
                'required' => true,
            ],
        ];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        return SkillResult::success('Test output');
    }
}

class SkillRegistryAnotherSkill extends Skill
{
    public function identifier(): string
    {
        return 'another-skill';
    }

    public function name(): string
    {
        return 'Another Skill';
    }

    public function description(): string
    {
        return 'Another skill for testing';
    }

    public function category(): string
    {
        return 'document';
    }

    public function tags(): array
    {
        return ['doc', 'example'];
    }

    public function parameters(): array
    {
        return [];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        return SkillResult::success();
    }
}

describe('SkillRegistry', function () {
    beforeEach(function () {
        $this->registry = new SkillRegistry;
    });

    it('registers a skill', function () {
        $skill = new SkillRegistryTestSkill;
        $this->registry->register($skill);

        expect($this->registry->has('test-skill'))->toBeTrue()
            ->and($this->registry->count())->toBe(1);
    });

    it('retrieves a skill by identifier', function () {
        $skill = new SkillRegistryTestSkill;
        $this->registry->register($skill);

        $retrieved = $this->registry->get('test-skill');

        expect($retrieved)->toBeInstanceOf(SkillInterface::class)
            ->and($retrieved->identifier())->toBe('test-skill');
    });

    it('returns null for non-existent skill', function () {
        expect($this->registry->get('non-existent'))->toBeNull();
    });

    it('returns all registered skills', function () {
        $this->registry->register(new SkillRegistryTestSkill);
        $this->registry->register(new SkillRegistryAnotherSkill);

        $all = $this->registry->all();

        expect($all)->toHaveCount(2)
            ->and($all)->toHaveKey('test-skill')
            ->and($all)->toHaveKey('another-skill');
    });

    it('filters skills by category', function () {
        $this->registry->register(new SkillRegistryTestSkill);       // category: testing
        $this->registry->register(new SkillRegistryAnotherSkill); // category: document

        $testingSkills = $this->registry->byCategory('testing');
        $documentSkills = $this->registry->byCategory('document');

        expect($testingSkills)->toHaveCount(1)
            ->and($documentSkills)->toHaveCount(1)
            ->and(array_key_first($testingSkills))->toBe('test-skill');
    });

    it('filters skills by tag', function () {
        $this->registry->register(new SkillRegistryTestSkill);       // tags: test, example
        $this->registry->register(new SkillRegistryAnotherSkill); // tags: doc, example

        $exampleSkills = $this->registry->byTag('example');
        $testSkills = $this->registry->byTag('test');
        $docSkills = $this->registry->byTag('doc');

        expect($exampleSkills)->toHaveCount(2)
            ->and($testSkills)->toHaveCount(1)
            ->and($docSkills)->toHaveCount(1);
    });

    it('returns all categories', function () {
        $this->registry->register(new SkillRegistryTestSkill);
        $this->registry->register(new SkillRegistryAnotherSkill);

        $categories = $this->registry->categories();

        expect($categories)->toContain('testing')
            ->and($categories)->toContain('document')
            ->and($categories)->toHaveCount(2);
    });

    it('returns all tags', function () {
        $this->registry->register(new SkillRegistryTestSkill);
        $this->registry->register(new SkillRegistryAnotherSkill);

        $tags = $this->registry->tags();

        expect($tags)->toContain('test')
            ->and($tags)->toContain('example')
            ->and($tags)->toContain('doc');
    });

    it('searches skills by query', function () {
        $this->registry->register(new SkillRegistryTestSkill);
        $this->registry->register(new SkillRegistryAnotherSkill);

        $results = $this->registry->search('test');

        expect($results)->toHaveCount(2); // Both contain 'test' in name or description

        $results = $this->registry->search('another');

        expect($results)->toHaveCount(1)
            ->and(array_key_first($results))->toBe('another-skill');
    });

    it('returns metadata for all skills', function () {
        $this->registry->register(new SkillRegistryTestSkill);

        $metadata = $this->registry->metadata();

        expect($metadata)->toHaveKey('test-skill')
            ->and($metadata['test-skill'])->toHaveKey('identifier')
            ->and($metadata['test-skill'])->toHaveKey('name')
            ->and($metadata['test-skill'])->toHaveKey('description')
            ->and($metadata['test-skill'])->toHaveKey('category')
            ->and($metadata['test-skill'])->toHaveKey('tags')
            ->and($metadata['test-skill'])->toHaveKey('parameters');
    });

    it('removes a skill', function () {
        $this->registry->register(new SkillRegistryTestSkill);

        expect($this->registry->has('test-skill'))->toBeTrue();

        $this->registry->remove('test-skill');

        expect($this->registry->has('test-skill'))->toBeFalse();
    });

    it('counts registered skills', function () {
        expect($this->registry->count())->toBe(0);

        $this->registry->register(new SkillRegistryTestSkill);

        expect($this->registry->count())->toBe(1);

        $this->registry->register(new SkillRegistryAnotherSkill);

        expect($this->registry->count())->toBe(2);
    });
});
