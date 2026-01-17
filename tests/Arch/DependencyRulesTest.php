<?php

declare(strict_types=1);

/**
 * Dependency Rules Tests
 *
 * Enforces proper dependency direction and isolation.
 */
arch('contracts do not depend on concrete implementations')
    ->expect('LaraForge\Generators\Contracts')
    ->not->toUse([
        'LaraForge\Generators\Generator',
        'LaraForge\Generators\GeneratorRegistry',
    ]);

arch('skill contracts do not depend on concrete implementations')
    ->expect('LaraForge\Skills\Contracts')
    ->not->toUse([
        'LaraForge\Skills\Skill',
        'LaraForge\Skills\SkillRegistry',
        'LaraForge\Skills\SkillResult',
    ]);

arch('skills do not depend on commands')
    ->expect('LaraForge\Skills')
    ->not->toUse('LaraForge\Commands');

arch('generators do not depend on commands')
    ->expect('LaraForge\Generators')
    ->not->toUse('LaraForge\Commands');
