<?php

declare(strict_types=1);

/**
 * Architectural Tests for LaraForge
 *
 * These tests enforce coding standards, architectural patterns,
 * and structural rules across the codebase.
 */
arch('source files use strict types')
    ->expect('LaraForge')
    ->toUseStrictTypes();

arch('contracts namespace contains only interfaces')
    ->expect('LaraForge\Generators\Contracts')
    ->toBeInterfaces();

arch('skill contracts are interfaces')
    ->expect('LaraForge\Skills\Contracts')
    ->toBeInterfaces();

arch('adapter contracts are interfaces')
    ->expect('LaraForge\Adapters\Contracts')
    ->toBeInterfaces();

arch('agent contracts are interfaces')
    ->expect('LaraForge\Agents\Contracts')
    ->toBeInterfaces();

arch('workflow contracts are interfaces')
    ->expect('LaraForge\Workflows\Contracts')
    ->toBeInterfaces();

arch('document contracts are interfaces')
    ->expect('LaraForge\Documents\Contracts')
    ->toBeInterfaces();

arch('hook contracts are interfaces')
    ->expect('LaraForge\Hooks\Contracts')
    ->toBeInterfaces();

arch('project contracts are interfaces')
    ->expect('LaraForge\Project\Contracts')
    ->toBeInterfaces();
