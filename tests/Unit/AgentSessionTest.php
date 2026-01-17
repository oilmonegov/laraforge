<?php

declare(strict_types=1);

use LaraForge\Worktree\AgentSession;

describe('AgentSession', function () {
    it('creates a session with factory method', function () {
        $session = AgentSession::create(
            path: '/path/to/worktree',
            branch: 'feature/user-auth',
            featureId: 'user-auth',
            agentId: 'developer-1',
        );

        expect($session->path())->toBe('/path/to/worktree')
            ->and($session->branch())->toBe('feature/user-auth')
            ->and($session->featureId())->toBe('user-auth')
            ->and($session->agentId())->toBe('developer-1')
            ->and($session->status())->toBe('active')
            ->and($session->isActive())->toBeTrue()
            ->and($session->id())->toContain('user-auth-developer-1');
    });

    it('manages status', function () {
        $session = AgentSession::create('/path', 'branch', 'feature', 'agent');

        expect($session->isActive())->toBeTrue()
            ->and($session->isPaused())->toBeFalse();

        $session->setStatus('paused');

        expect($session->isActive())->toBeFalse()
            ->and($session->isPaused())->toBeTrue();

        $session->setStatus('completed');

        expect($session->isCompleted())->toBeTrue();

        $session->setStatus('merged');

        expect($session->isMerged())->toBeTrue();

        $session->setStatus('abandoned');

        expect($session->isAbandoned())->toBeTrue();
    });

    it('tracks modified files', function () {
        $session = AgentSession::create('/path', 'branch', 'feature', 'agent');

        expect($session->modifiedFiles())->toBe([]);

        $session->addModifiedFile('src/Controller.php');
        $session->addModifiedFile('src/Model.php');
        $session->addModifiedFile('src/Controller.php'); // Duplicate

        expect($session->modifiedFiles())->toHaveCount(2)
            ->and($session->modifiedFiles())->toContain('src/Controller.php')
            ->and($session->modifiedFiles())->toContain('src/Model.php');
    });

    it('tracks commits', function () {
        $session = AgentSession::create('/path', 'branch', 'feature', 'agent');

        expect($session->commits())->toBe([]);

        $session->addCommit('abc123', 'Initial commit');
        $session->addCommit('def456', 'Fix bug');

        $commits = $session->commits();

        expect($commits)->toHaveCount(2)
            ->and($commits[0]['hash'])->toBe('abc123')
            ->and($commits[0]['message'])->toBe('Initial commit')
            ->and($commits[0])->toHaveKey('timestamp')
            ->and($commits[1]['hash'])->toBe('def456');
    });

    it('manages metadata', function () {
        $session = AgentSession::create('/path', 'branch', 'feature', 'agent');

        expect($session->getMetadata('key'))->toBeNull()
            ->and($session->getMetadata('key', 'default'))->toBe('default');

        $session->setMetadata('key', 'value');
        $session->setMetadata('count', 42);

        expect($session->getMetadata('key'))->toBe('value')
            ->and($session->getMetadata('count'))->toBe(42)
            ->and($session->metadata())->toHaveKey('key')
            ->and($session->metadata())->toHaveKey('count');
    });

    it('tracks activity timestamps', function () {
        $session = AgentSession::create('/path', 'branch', 'feature', 'agent');

        $createdAt = $session->createdAt();
        $lastActivity = $session->lastActivityAt();

        usleep(10000); // 10ms delay

        $session->touch();

        expect($session->createdAt())->toBe($createdAt)
            ->and($session->lastActivityAt())->not->toBe($lastActivity);
    });

    it('updates activity on modifications', function () {
        $session = AgentSession::create('/path', 'branch', 'feature', 'agent');

        $lastActivity = $session->lastActivityAt();

        usleep(10000);

        $session->addModifiedFile('file.php');

        expect($session->lastActivityAt())->not->toBe($lastActivity);

        $lastActivity = $session->lastActivityAt();

        usleep(10000);

        $session->addCommit('hash', 'message');

        expect($session->lastActivityAt())->not->toBe($lastActivity);

        $lastActivity = $session->lastActivityAt();

        usleep(10000);

        $session->setStatus('completed');

        expect($session->lastActivityAt())->not->toBe($lastActivity);
    });

    it('converts to array', function () {
        $session = AgentSession::create('/path/to/worktree', 'feature/test', 'test-feature', 'agent-1');
        $session->addModifiedFile('file.php');
        $session->addCommit('abc123', 'Commit message');
        $session->setMetadata('key', 'value');

        $array = $session->toArray();

        expect($array)->toHaveKey('id')
            ->and($array)->toHaveKey('path')
            ->and($array['path'])->toBe('/path/to/worktree')
            ->and($array)->toHaveKey('branch')
            ->and($array['branch'])->toBe('feature/test')
            ->and($array)->toHaveKey('feature_id')
            ->and($array['feature_id'])->toBe('test-feature')
            ->and($array)->toHaveKey('agent_id')
            ->and($array['agent_id'])->toBe('agent-1')
            ->and($array)->toHaveKey('status')
            ->and($array['status'])->toBe('active')
            ->and($array)->toHaveKey('modified_files')
            ->and($array['modified_files'])->toContain('file.php')
            ->and($array)->toHaveKey('commits')
            ->and($array['commits'])->toHaveCount(1)
            ->and($array)->toHaveKey('metadata')
            ->and($array['metadata'])->toHaveKey('key')
            ->and($array)->toHaveKey('created_at')
            ->and($array)->toHaveKey('last_activity_at');
    });

    it('creates from array', function () {
        $data = [
            'id' => 'session-123',
            'path' => '/worktree/path',
            'branch' => 'feature/auth',
            'feature_id' => 'auth-feature',
            'agent_id' => 'dev-1',
            'status' => 'paused',
            'modified_files' => ['file1.php', 'file2.php'],
            'commits' => [
                ['hash' => 'abc', 'message' => 'msg', 'timestamp' => '2024-01-01T00:00:00+00:00'],
            ],
            'metadata' => ['key' => 'value'],
            'created_at' => '2024-01-01T00:00:00+00:00',
            'last_activity_at' => '2024-01-02T00:00:00+00:00',
        ];

        $session = AgentSession::fromArray($data);

        expect($session->id())->toBe('session-123')
            ->and($session->path())->toBe('/worktree/path')
            ->and($session->branch())->toBe('feature/auth')
            ->and($session->featureId())->toBe('auth-feature')
            ->and($session->agentId())->toBe('dev-1')
            ->and($session->status())->toBe('paused')
            ->and($session->isPaused())->toBeTrue()
            ->and($session->modifiedFiles())->toHaveCount(2)
            ->and($session->commits())->toHaveCount(1)
            ->and($session->getMetadata('key'))->toBe('value');
    });

    it('round-trips through array conversion', function () {
        $original = AgentSession::create('/path', 'branch', 'feature', 'agent');
        $original->setStatus('completed');
        $original->addModifiedFile('file.php');
        $original->addCommit('hash', 'message');
        $original->setMetadata('key', 'value');

        $array = $original->toArray();
        $restored = AgentSession::fromArray($array);

        expect($restored->id())->toBe($original->id())
            ->and($restored->path())->toBe($original->path())
            ->and($restored->branch())->toBe($original->branch())
            ->and($restored->featureId())->toBe($original->featureId())
            ->and($restored->agentId())->toBe($original->agentId())
            ->and($restored->status())->toBe($original->status())
            ->and($restored->modifiedFiles())->toBe($original->modifiedFiles())
            ->and(count($restored->commits()))->toBe(count($original->commits()))
            ->and($restored->getMetadata('key'))->toBe($original->getMetadata('key'));
    });
});
