<?php

declare(strict_types=1);

use LaraForge\Adapters\ClaudeCodeAdapter;
use LaraForge\Adapters\GenericAgentAdapter;
use LaraForge\Agents\Task;
use LaraForge\Project\ProjectContext;
use LaraForge\Project\ProjectState;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillRegistry;
use LaraForge\Skills\SkillResult;
use Symfony\Component\Filesystem\Filesystem;

class AgentAdapterTestSkill extends Skill
{
    public function identifier(): string
    {
        return 'mock-skill';
    }

    public function name(): string
    {
        return 'Mock Skill';
    }

    public function description(): string
    {
        return 'A mock skill for testing';
    }

    public function category(): string
    {
        return 'testing';
    }

    public function tags(): array
    {
        return ['mock', 'test'];
    }

    public function parameters(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'Name parameter',
                'required' => true,
            ],
            'count' => [
                'type' => 'integer',
                'description' => 'Count parameter',
                'default' => 1,
            ],
        ];
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        return SkillResult::success('Executed with: '.json_encode($params));
    }
}

describe('GenericAgentAdapter', function () {
    beforeEach(function () {
        $this->tempDir = createTempDirectory();
        $this->filesystem = new Filesystem;
        $this->laraforge = laraforge($this->tempDir);
        $this->skills = new SkillRegistry($this->laraforge);
        $this->skills->register(new AgentAdapterTestSkill);
        $this->adapter = new GenericAgentAdapter($this->skills);
    });

    afterEach(function () {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    });

    it('returns adapter metadata', function () {
        expect($this->adapter->identifier())->toBe('generic')
            ->and($this->adapter->name())->toBe('Generic Agent Adapter')
            ->and($this->adapter->isAvailable())->toBeTrue();
    });

    it('executes a skill', function () {
        $skill = $this->skills->get('mock-skill');
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $state,
        );

        $result = $this->adapter->executeSkill($skill, ['name' => 'test'], $context);

        expect($result)->toHaveKey('success')
            ->and($result['success'])->toBeTrue()
            ->and($result)->toHaveKey('output')
            ->and($result)->toHaveKey('artifacts')
            ->and($result)->toHaveKey('next_steps')
            ->and($result)->toHaveKey('error')
            ->and($result)->toHaveKey('metadata');
    });

    it('executes a task', function () {
        $task = Task::feature('Test Feature', 'A test feature');
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $state,
        );

        $result = $this->adapter->executeTask($task, $context);

        expect($result)->toHaveKey('success')
            ->and($result['success'])->toBeTrue()
            ->and($result)->toHaveKey('output')
            ->and($result['output'])->toContain('Test Feature')
            ->and($result)->toHaveKey('task');
    });

    it('returns context metadata', function () {
        $metadata = $this->adapter->getContextMetadata();

        expect($metadata)->toHaveKey('adapter')
            ->and($metadata['adapter'])->toBe('generic')
            ->and($metadata)->toHaveKey('skills')
            ->and($metadata['skills'])->toContain('mock-skill')
            ->and($metadata)->toHaveKey('categories')
            ->and($metadata)->toHaveKey('capabilities')
            ->and($metadata['capabilities']['parallel_execution'])->toBeFalse()
            ->and($metadata['capabilities']['worktree_support'])->toBeTrue()
            ->and($metadata['capabilities']['mcp_integration'])->toBeFalse();
    });

    it('formats output', function () {
        expect($this->adapter->formatOutput('string'))->toBe('string');
        expect($this->adapter->formatOutput(['key' => 'value']))->toContain('key');
        expect($this->adapter->formatOutput(123))->toBe('123');
    });

    it('lists skills', function () {
        $skills = $this->adapter->listSkills();

        expect($skills)->toHaveKey('mock-skill')
            ->and($skills['mock-skill'])->toBe('A mock skill for testing');
    });

    it('generates skill documentation', function () {
        $doc = $this->adapter->getSkillDocumentation('mock-skill');

        expect($doc)->toContain('# Mock Skill')
            ->and($doc)->toContain('**Identifier:** `mock-skill`')
            ->and($doc)->toContain('**Category:** testing')
            ->and($doc)->toContain('## Parameters')
            ->and($doc)->toContain('`name`')
            ->and($doc)->toContain('(required)')
            ->and($doc)->toContain('## Usage');

        $nonExistent = $this->adapter->getSkillDocumentation('non-existent');

        expect($nonExistent)->toBeNull();
    });

    it('generates help text', function () {
        $help = $this->adapter->generateHelpText();

        expect($help)->toContain('# LaraForge Skills')
            ->and($help)->toContain('## Testing')
            ->and($help)->toContain('mock-skill');
    });
});

describe('ClaudeCodeAdapter', function () {
    beforeEach(function () {
        $this->tempDir = createTempDirectory();
        $this->filesystem = new Filesystem;
        $this->laraforge = laraforge($this->tempDir);
        $this->skills = new SkillRegistry($this->laraforge);
        $this->skills->register(new AgentAdapterTestSkill);
        $this->adapter = new ClaudeCodeAdapter($this->skills);
    });

    afterEach(function () {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    });

    it('returns adapter metadata', function () {
        expect($this->adapter->identifier())->toBe('claude-code')
            ->and($this->adapter->name())->toBe('Claude Code Adapter');
    });

    it('executes a skill', function () {
        $skill = $this->skills->get('mock-skill');
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $state,
        );

        $result = $this->adapter->executeSkill($skill, ['name' => 'test'], $context);

        expect($result)->toHaveKey('success')
            ->and($result['success'])->toBeTrue()
            ->and($result)->toHaveKey('output')
            ->and($result)->toHaveKey('artifacts')
            ->and($result)->toHaveKey('next_steps')
            ->and($result)->toHaveKey('error');
    });

    it('executes a task with formatted output', function () {
        $task = Task::feature('Test Feature', 'A test feature');
        $task->setStatus('in_progress');
        $state = ProjectState::initialize($this->tempDir, 'Test');
        $context = new ProjectContext(
            laraforge: $this->laraforge,
            state: $state,
        );

        $result = $this->adapter->executeTask($task, $context);

        expect($result)->toHaveKey('success')
            ->and($result['success'])->toBeTrue()
            ->and($result)->toHaveKey('output')
            ->and($result['output'])->toContain('## Task: Test Feature')
            ->and($result['output'])->toContain('**Type:** feature')
            ->and($result['output'])->toContain('**Status:** in_progress')
            ->and($result)->toHaveKey('task');
    });

    it('returns context metadata with instructions', function () {
        $metadata = $this->adapter->getContextMetadata();

        expect($metadata)->toHaveKey('adapter')
            ->and($metadata['adapter'])->toBe('claude-code')
            ->and($metadata)->toHaveKey('skills')
            ->and($metadata)->toHaveKey('capabilities')
            ->and($metadata['capabilities']['parallel_execution'])->toBeTrue()
            ->and($metadata['capabilities']['worktree_support'])->toBeTrue()
            ->and($metadata['capabilities']['mcp_integration'])->toBeTrue()
            ->and($metadata)->toHaveKey('instructions');
    });

    it('returns agent instructions', function () {
        $instructions = $this->adapter->getAgentInstructions();

        expect($instructions)->toHaveKey('skill_usage')
            ->and($instructions)->toHaveKey('document_workflow')
            ->and($instructions)->toHaveKey('parallel_work')
            ->and($instructions)->toHaveKey('quality_standards');
    });

    it('generates MCP manifest', function () {
        $manifest = $this->adapter->generateMcpManifest();

        expect($manifest)->toHaveKey('name')
            ->and($manifest['name'])->toBe('laraforge')
            ->and($manifest)->toHaveKey('version')
            ->and($manifest)->toHaveKey('description')
            ->and($manifest)->toHaveKey('tools')
            ->and($manifest['tools'])->toBeArray();

        // Check that skill is converted to tool
        $toolNames = array_column($manifest['tools'], 'name');

        expect($toolNames)->toContain('laraforge_mock-skill');

        $mockTool = null;
        foreach ($manifest['tools'] as $tool) {
            if ($tool['name'] === 'laraforge_mock-skill') {
                $mockTool = $tool;
                break;
            }
        }

        expect($mockTool)->toHaveKey('description')
            ->and($mockTool)->toHaveKey('inputSchema')
            ->and($mockTool['inputSchema']['properties'])->toHaveKey('name')
            ->and($mockTool['inputSchema']['properties'])->toHaveKey('count')
            ->and($mockTool['inputSchema']['required'])->toContain('name');
    });

    it('formats string output', function () {
        expect($this->adapter->formatOutput('test string'))->toBe('test string');
    });

    it('formats array output', function () {
        $output = $this->adapter->formatOutput([
            'status' => 'success',
            'files' => ['file1.php', 'file2.php'],
        ]);

        expect($output)->toContain('**status:** success')
            ->and($output)->toContain('**files:**');
    });

    it('formats numeric array output', function () {
        $output = $this->adapter->formatOutput(['item1', 'item2', 'item3']);

        expect($output)->toContain('- item1')
            ->and($output)->toContain('- item2')
            ->and($output)->toContain('- item3');
    });
});
