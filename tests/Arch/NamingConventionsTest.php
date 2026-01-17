<?php

declare(strict_types=1);

/**
 * Naming Convention Tests
 *
 * Enforces consistent naming patterns across the codebase.
 */
arch('interfaces are suffixed with Interface')
    ->expect('LaraForge\Generators\Contracts')
    ->toHaveSuffix('Interface');

arch('skill interfaces are suffixed with Interface')
    ->expect('LaraForge\Skills\Contracts')
    ->toHaveSuffix('Interface');

arch('adapter interfaces are suffixed with Interface')
    ->expect('LaraForge\Adapters\Contracts')
    ->toHaveSuffix('Interface');

arch('agent interfaces are suffixed with Interface')
    ->expect('LaraForge\Agents\Contracts')
    ->toHaveSuffix('Interface');

arch('workflow interfaces are suffixed with Interface')
    ->expect('LaraForge\Workflows\Contracts')
    ->toHaveSuffix('Interface');

arch('document interfaces are suffixed with Interface')
    ->expect('LaraForge\Documents\Contracts')
    ->toHaveSuffix('Interface');

arch('hook interfaces are suffixed with Interface')
    ->expect('LaraForge\Hooks\Contracts')
    ->toHaveSuffix('Interface');

arch('project interfaces are suffixed with Interface')
    ->expect('LaraForge\Project\Contracts')
    ->toHaveSuffix('Interface');
