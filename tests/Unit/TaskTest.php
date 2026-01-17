<?php

declare(strict_types=1);

use LaraForge\Agents\Task;

describe('Task', function () {
    it('creates a task with factory method', function () {
        $task = Task::create(
            type: 'feature',
            title: 'Add user authentication',
            description: 'Implement login and registration',
            params: ['scope' => 'full'],
            featureId: 'user-auth-123',
            priority: 1,
        );

        expect($task->id())->toStartWith('task_')
            ->and($task->type())->toBe('feature')
            ->and($task->title())->toBe('Add user authentication')
            ->and($task->description())->toBe('Implement login and registration')
            ->and($task->params())->toHaveKey('scope')
            ->and($task->featureId())->toBe('user-auth-123')
            ->and($task->priority())->toBe(1)
            ->and($task->status())->toBe('pending');
    });

    it('creates feature task', function () {
        $task = Task::feature('New feature', 'Description');

        expect($task->type())->toBe('feature')
            ->and($task->title())->toBe('New feature');
    });

    it('creates bugfix task', function () {
        $task = Task::bugfix('Fix login bug');

        expect($task->type())->toBe('bugfix')
            ->and($task->title())->toBe('Fix login bug');
    });

    it('creates refactor task', function () {
        $task = Task::refactor('Refactor auth module');

        expect($task->type())->toBe('refactor');
    });

    it('creates test task', function () {
        $task = Task::test('Add unit tests');

        expect($task->type())->toBe('test');
    });

    it('creates review task', function () {
        $task = Task::review('Code review PR #123');

        expect($task->type())->toBe('review');
    });

    it('manages status', function () {
        $task = Task::feature('Test');

        expect($task->isPending())->toBeTrue()
            ->and($task->isInProgress())->toBeFalse()
            ->and($task->isCompleted())->toBeFalse();

        $task->setStatus('in_progress');

        expect($task->isPending())->toBeFalse()
            ->and($task->isInProgress())->toBeTrue();

        $task->setStatus('completed');

        expect($task->isCompleted())->toBeTrue();

        $task->setStatus('failed');

        expect($task->isFailed())->toBeTrue();
    });

    it('manages assignee', function () {
        $task = Task::feature('Test');

        expect($task->assignee())->toBeNull();

        $task->setAssignee('developer-agent');

        expect($task->assignee())->toBe('developer-agent');
    });

    it('manages metadata', function () {
        $task = Task::feature('Test');

        expect($task->getMetadata('key'))->toBeNull()
            ->and($task->getMetadata('key', 'default'))->toBe('default');

        $task->setMetadata('key', 'value');

        expect($task->getMetadata('key'))->toBe('value');
    });

    it('retrieves params', function () {
        $task = Task::feature('Test', '', ['name' => 'John', 'age' => 30]);

        expect($task->getParam('name'))->toBe('John')
            ->and($task->getParam('age'))->toBe(30)
            ->and($task->getParam('missing'))->toBeNull()
            ->and($task->getParam('missing', 'default'))->toBe('default');
    });

    it('creates subtasks', function () {
        $parent = Task::feature('Parent task');
        $subtask = $parent->withSubtask('Subtask 1', 'Subtask description');

        expect($subtask->parentId())->toBe($parent->id())
            ->and($subtask->title())->toBe('Subtask 1')
            ->and($subtask->type())->toBe('feature')
            ->and($parent->subtaskIds())->toContain($subtask->id());
    });

    it('adds subtask ids', function () {
        $task = Task::feature('Test');

        expect($task->subtaskIds())->toBe([]);

        $task->addSubtask('subtask-1');
        $task->addSubtask('subtask-2');
        $task->addSubtask('subtask-1'); // Duplicate, should not add

        expect($task->subtaskIds())->toHaveCount(2)
            ->and($task->subtaskIds())->toContain('subtask-1')
            ->and($task->subtaskIds())->toContain('subtask-2');
    });

    it('checks if blocked', function () {
        $task = new Task(
            id: 'task-1',
            type: 'feature',
            title: 'Test',
            dependencies: ['task-0'],
        );

        expect($task->isBlocked())->toBeTrue()
            ->and($task->dependencies())->toContain('task-0');

        $unblocked = Task::feature('Unblocked');

        expect($unblocked->isBlocked())->toBeFalse();
    });

    it('converts to array', function () {
        $task = Task::feature('Test task', 'Description', ['key' => 'value']);

        $array = $task->toArray();

        expect($array)->toHaveKey('id')
            ->and($array)->toHaveKey('type')
            ->and($array['type'])->toBe('feature')
            ->and($array)->toHaveKey('title')
            ->and($array['title'])->toBe('Test task')
            ->and($array)->toHaveKey('description')
            ->and($array)->toHaveKey('status')
            ->and($array['status'])->toBe('pending')
            ->and($array)->toHaveKey('params')
            ->and($array['params'])->toHaveKey('key');
    });

    it('creates from array', function () {
        $data = [
            'id' => 'task-123',
            'type' => 'bugfix',
            'title' => 'Fix bug',
            'description' => 'Bug description',
            'status' => 'in_progress',
            'priority' => 2,
            'params' => ['severity' => 'high'],
            'assignee' => 'dev-1',
            'feature_id' => 'feat-1',
        ];

        $task = Task::fromArray($data);

        expect($task->id())->toBe('task-123')
            ->and($task->type())->toBe('bugfix')
            ->and($task->title())->toBe('Fix bug')
            ->and($task->status())->toBe('in_progress')
            ->and($task->priority())->toBe(2)
            ->and($task->getParam('severity'))->toBe('high')
            ->and($task->assignee())->toBe('dev-1')
            ->and($task->featureId())->toBe('feat-1');
    });

    it('round-trips through array conversion', function () {
        $original = Task::create(
            type: 'feature',
            title: 'Test',
            description: 'Description',
            params: ['key' => 'value'],
            featureId: 'feat-1',
            priority: 2,
        );
        $original->setStatus('in_progress');
        $original->setAssignee('agent-1');
        $original->setMetadata('custom', 'data');

        $array = $original->toArray();
        $restored = Task::fromArray($array);

        expect($restored->id())->toBe($original->id())
            ->and($restored->type())->toBe($original->type())
            ->and($restored->title())->toBe($original->title())
            ->and($restored->status())->toBe($original->status())
            ->and($restored->priority())->toBe($original->priority())
            ->and($restored->assignee())->toBe($original->assignee())
            ->and($restored->featureId())->toBe($original->featureId());
    });
});
