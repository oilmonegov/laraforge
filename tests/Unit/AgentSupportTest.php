<?php

declare(strict_types=1);

use LaraForge\AgentSupport\Agents\ClaudeCodeSupport;
use LaraForge\AgentSupport\Agents\CursorSupport;
use LaraForge\AgentSupport\Agents\JetBrainsSupport;
use LaraForge\AgentSupport\Agents\WindsurfSupport;
use LaraForge\AgentSupport\AgentSupportFactory;
use LaraForge\AgentSupport\AgentSupportRegistry;

describe('AgentSupportRegistry', function () {
    it('can register and retrieve agent supports', function () {
        $registry = new AgentSupportRegistry;
        $support = new ClaudeCodeSupport;

        $registry->register($support);

        expect($registry->has('claude-code'))->toBeTrue();
        expect($registry->get('claude-code'))->toBe($support);
    });

    it('returns null for unknown agent', function () {
        $registry = new AgentSupportRegistry;

        expect($registry->get('unknown'))->toBeNull();
        expect($registry->has('unknown'))->toBeFalse();
    });

    it('returns all registered agents', function () {
        $registry = new AgentSupportRegistry;
        $registry->register(new ClaudeCodeSupport);
        $registry->register(new CursorSupport);

        $all = $registry->all();

        expect($all)->toHaveKeys(['claude-code', 'cursor']);
        expect($registry->count())->toBe(2);
    });

    it('returns agents sorted by priority', function () {
        $registry = new AgentSupportRegistry;
        $registry->register(new CursorSupport);  // Priority 80
        $registry->register(new ClaudeCodeSupport);  // Priority 100
        $registry->register(new WindsurfSupport);  // Priority 75

        $sorted = $registry->allByPriority();

        expect($sorted[0]->identifier())->toBe('claude-code');
        expect($sorted[1]->identifier())->toBe('cursor');
        expect($sorted[2]->identifier())->toBe('windsurf');
    });

    it('returns prompt options in priority order', function () {
        $registry = AgentSupportFactory::create();

        $options = $registry->getPromptOptions();

        expect($options)->toHaveKey('claude-code');
        expect($options)->toHaveKey('cursor');
        expect($options)->toHaveKey('jetbrains');
        expect($options)->toHaveKey('windsurf');

        // First key should be highest priority
        $keys = array_keys($options);
        expect($keys[0])->toBe('claude-code');
    });

    it('returns metadata for all agents', function () {
        $registry = AgentSupportFactory::create();

        $metadata = $registry->metadata();

        expect($metadata)->toHaveKey('claude-code');
        expect($metadata['claude-code'])->toHaveKeys([
            'identifier',
            'name',
            'description',
            'spec_version',
            'capabilities',
            'root_files',
            'documentation_url',
            'priority',
        ]);
    });
});

describe('ClaudeCodeSupport', function () {
    it('has correct identifier', function () {
        $support = new ClaudeCodeSupport;

        expect($support->identifier())->toBe('claude-code');
    });

    it('has correct name', function () {
        $support = new ClaudeCodeSupport;

        expect($support->name())->toBe('Claude Code');
    });

    it('returns CLAUDE.md as root file', function () {
        $support = new ClaudeCodeSupport;

        expect($support->getRootFiles())->toContain('CLAUDE.md');
    });

    it('has all expected capabilities', function () {
        $support = new ClaudeCodeSupport;

        $capabilities = $support->getCapabilities();

        expect($capabilities)->toHaveKey('skills');
        expect($capabilities)->toHaveKey('commands');
        expect($capabilities)->toHaveKey('sub_agents');
        expect($capabilities)->toHaveKey('mcp_server');
        expect($capabilities['skills'])->toBeTrue();
    });

    it('has highest priority', function () {
        $support = new ClaudeCodeSupport;

        expect($support->priority())->toBe(100);
    });

    it('generates valid main config', function () {
        $support = new ClaudeCodeSupport;

        $config = $support->generateMainConfig([
            'project' => ['name' => 'Test Project', 'description' => 'A test project'],
            'framework' => 'laravel',
            'docs' => [],
            'criteriaFiles' => [],
        ]);

        expect($config)->toContain('# Test Project');
        expect($config)->toContain('laravel');
        expect($config)->toContain('LaraForge');
    });

    it('is applicable to any project', function () {
        $support = new ClaudeCodeSupport;

        expect($support->isApplicable('/any/path'))->toBeTrue();
    });

    it('returns correct skill format', function () {
        $support = new ClaudeCodeSupport;

        $format = $support->getSkillFormat();

        expect($format)->toHaveKey('format');
        expect($format)->toHaveKey('location');
        expect($format['format'])->toBe('markdown');
    });

    it('formats skills correctly', function () {
        $support = new ClaudeCodeSupport;

        $formatted = $support->formatSkill([
            'name' => 'Test Skill',
            'description' => 'A test skill',
            'trigger' => '/test',
            'instructions' => 'Do something',
        ]);

        expect($formatted)->toContain('# Test Skill');
        expect($formatted)->toContain('A test skill');
        expect($formatted)->toContain('/test');
    });
});

describe('CursorSupport', function () {
    it('has correct identifier', function () {
        $support = new CursorSupport;

        expect($support->identifier())->toBe('cursor');
    });

    it('returns .cursorrules as root file', function () {
        $support = new CursorSupport;

        expect($support->getRootFiles())->toContain('.cursorrules');
    });

    it('has expected capabilities', function () {
        $support = new CursorSupport;

        $capabilities = $support->getCapabilities();

        expect($capabilities)->toHaveKey('rules');
        expect($capabilities)->toHaveKey('codebase_indexing');
        expect($capabilities['rules'])->toBeTrue();
    });

    it('generates valid cursorrules content', function () {
        $support = new CursorSupport;

        $config = $support->generateMainConfig([
            'project' => ['name' => 'Test Project', 'description' => 'A test'],
            'framework' => 'laravel',
            'docs' => [],
            'criteriaFiles' => [],
        ]);

        expect($config)->toContain('Test Project');
        expect($config)->toContain('Cursor Rules');
        expect($config)->toContain('declare(strict_types=1)');
    });
});

describe('JetBrainsSupport', function () {
    it('has correct identifier', function () {
        $support = new JetBrainsSupport;

        expect($support->identifier())->toBe('jetbrains');
    });

    it('returns .jb-ai-context.md as root file', function () {
        $support = new JetBrainsSupport;

        expect($support->getRootFiles())->toContain('.jb-ai-context.md');
    });

    it('is only applicable when .idea directory exists', function () {
        $support = new JetBrainsSupport;

        // Should return false for non-existent path
        expect($support->isApplicable('/nonexistent/path'))->toBeFalse();
    });

    it('has expected capabilities', function () {
        $support = new JetBrainsSupport;

        $capabilities = $support->getCapabilities();

        expect($capabilities)->toHaveKey('ai_context');
        expect($capabilities)->toHaveKey('inspections');
        expect($capabilities)->toHaveKey('live_templates');
    });
});

describe('WindsurfSupport', function () {
    it('has correct identifier', function () {
        $support = new WindsurfSupport;

        expect($support->identifier())->toBe('windsurf');
    });

    it('returns .windsurfrules as root file', function () {
        $support = new WindsurfSupport;

        expect($support->getRootFiles())->toContain('.windsurfrules');
    });

    it('has expected capabilities', function () {
        $support = new WindsurfSupport;

        $capabilities = $support->getCapabilities();

        expect($capabilities)->toHaveKey('rules');
        expect($capabilities)->toHaveKey('cascade_flows');
        expect($capabilities)->toHaveKey('memories');
    });

    it('generates valid windsurfrules content', function () {
        $support = new WindsurfSupport;

        $config = $support->generateMainConfig([
            'project' => ['name' => 'Test Project', 'description' => 'A test'],
            'framework' => 'laravel',
            'docs' => [],
            'criteriaFiles' => [],
        ]);

        expect($config)->toContain('Test Project');
        expect($config)->toContain('Windsurf Rules');
        expect($config)->toContain('Cascade');
    });
});

describe('AgentSupportFactory', function () {
    it('creates registry with all agents', function () {
        $registry = AgentSupportFactory::create();

        expect($registry->count())->toBe(4);
        expect($registry->has('claude-code'))->toBeTrue();
        expect($registry->has('cursor'))->toBeTrue();
        expect($registry->has('jetbrains'))->toBeTrue();
        expect($registry->has('windsurf'))->toBeTrue();
    });

    it('returns available agents list', function () {
        $agents = AgentSupportFactory::availableAgents();

        expect($agents)->toContain('claude-code');
        expect($agents)->toContain('cursor');
        expect($agents)->toContain('jetbrains');
        expect($agents)->toContain('windsurf');
    });

    it('returns claude-code as primary agent', function () {
        expect(AgentSupportFactory::primaryAgent())->toBe('claude-code');
    });

    it('returns agent combinations', function () {
        $combinations = AgentSupportFactory::agentCombinations();

        expect($combinations)->toHaveKeys(['minimal', 'standard', 'comprehensive']);
        expect($combinations['minimal'])->toContain('claude-code');
        expect($combinations['comprehensive'])->toHaveCount(4);
    });
});
