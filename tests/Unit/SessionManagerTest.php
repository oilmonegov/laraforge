<?php

declare(strict_types=1);

use LaraForge\Session\Session;
use LaraForge\Session\SessionManager;

describe('SessionManager', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/laraforge-session-test-'.uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir.'/.laraforge', 0755, true);
        $this->manager = new SessionManager($this->tempDir);
    });

    afterEach(function (): void {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tempDir);
    });

    it('detects git is not initialized', function (): void {
        expect($this->manager->isGitInitialized())->toBeFalse();
    });

    it('detects git is initialized', function (): void {
        mkdir($this->tempDir.'/.git');

        $manager = new SessionManager($this->tempDir);
        expect($manager->isGitInitialized())->toBeTrue();
    });

    it('can start a session', function (): void {
        $session = $this->manager->startSession('feature', 'Test Feature');

        expect($session)->toBeInstanceOf(Session::class);
        expect($session->workflowType)->toBe('feature');
        expect($session->workflowName)->toBe('Test Feature');
    });

    it('generates session id with expected format', function (): void {
        $id = $this->manager->currentSessionId();

        // Should contain hostname, timestamp, and PID
        expect($id)->toMatch('/^[\w\-\.]+-\d{14}-\d+$/');
    });

    it('detects no active sessions initially', function (): void {
        expect($this->manager->getActiveSessions())->toBeEmpty();
    });

    it('detects no conflict when alone', function (): void {
        expect($this->manager->detectConflict())->toBeNull();
    });

    it('can end a session', function (): void {
        $this->manager->startSession('feature', 'Test');
        $this->manager->endSession();

        // Create new manager to check sessions
        $manager2 = new SessionManager($this->tempDir);
        expect($manager2->getActiveSessions())->toBeEmpty();
    });

    it('cleans up stale sessions', function (): void {
        // Create a fake stale session
        $sessionsPath = $this->tempDir.'/.laraforge/sessions.yaml';
        $staleData = [
            'stale-session' => [
                'branch' => 'main',
                'last_activity' => date('c', time() - 600), // 10 minutes ago
                'pid' => 99999999, // Non-existent PID
            ],
        ];
        file_put_contents($sessionsPath, \Symfony\Component\Yaml\Yaml::dump($staleData));

        $cleaned = $this->manager->cleanupStaleSessions();

        expect($cleaned)->toBe(1);
    });

    it('suggests worktree name', function (): void {
        $name = $this->manager->suggestWorktreeName('feature', 'User Auth');

        expect($name)->toContain('feature');
        expect($name)->toContain('user-auth');
    });

    it('suggests worktree name with timestamp when no name', function (): void {
        $name = $this->manager->suggestWorktreeName();

        expect($name)->toContain('session');
        expect($name)->toMatch('/session-\d{8}-\d{6}/');
    });
});

describe('Session', function (): void {
    it('creates a session with all properties', function (): void {
        $session = new Session(
            id: 'test-123',
            branch: 'feature/test',
            worktree: null,
            workflowType: 'feature',
            workflowName: 'Test Feature',
            startedAt: '2024-01-01T00:00:00+00:00',
            lastActivity: '2024-01-01T00:00:00+00:00',
            pid: 12345,
        );

        expect($session->id)->toBe('test-123');
        expect($session->branch)->toBe('feature/test');
        expect($session->workflowType)->toBe('feature');
    });

    it('provides description', function (): void {
        $session = new Session(
            id: 'test',
            branch: 'main',
            worktree: null,
            workflowType: 'feature',
            workflowName: 'Auth System',
            startedAt: '',
            lastActivity: '',
            pid: null,
        );

        $desc = $session->description();

        expect($desc)->toContain('Feature');
        expect($desc)->toContain('Auth System');
        expect($desc)->toContain('main');
    });

    it('converts to array', function (): void {
        $session = new Session(
            id: 'test',
            branch: 'main',
            worktree: null,
            workflowType: 'feature',
            workflowName: 'Test',
            startedAt: '2024-01-01',
            lastActivity: '2024-01-01',
            pid: 123,
        );

        $array = $session->toArray();

        expect($array)->toHaveKey('id');
        expect($array)->toHaveKey('branch');
        expect($array)->toHaveKey('workflow_type');
        expect($array['id'])->toBe('test');
    });
});
