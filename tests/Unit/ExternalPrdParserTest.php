<?php

declare(strict_types=1);

use LaraForge\Documents\Parsers\ExternalPrdParser;
use LaraForge\Documents\ProductRequirements;

describe('ExternalPrdParser', function (): void {
    beforeEach(function (): void {
        $this->parser = new ExternalPrdParser;
    });

    it('can parse markdown PRD with objectives', function (): void {
        $content = <<<'MARKDOWN'
# User Authentication Feature

## Overview
Implement secure user authentication for the platform.

## Objectives
- Provide secure login functionality
- Support social authentication
- Enable two-factor authentication

## Requirements
- [REQ-1] User can register with email and password (high)
- [REQ-2] User can login with valid credentials
- [REQ-3] User can reset forgotten password

## User Stories
As a user, I want to login with my email so that I can access my account.
As a user, I want to reset my password so that I can recover my account.
MARKDOWN;

        $prd = $this->parser->parse($content);

        expect($prd)->toBeInstanceOf(ProductRequirements::class);
        expect($prd->title())->toBe('User Authentication Feature');
        expect($prd->objectives())->toHaveCount(3);
        expect($prd->requirements())->toHaveCount(3);
        expect($prd->userStories())->toHaveCount(2);
    });

    it('extracts problem statement from overview section', function (): void {
        $content = <<<'MARKDOWN'
# Feature Name

## Problem Statement
Users currently have no way to authenticate, leading to security issues.

## Objectives
- Solve the auth problem
MARKDOWN;

        $prd = $this->parser->parse($content);

        expect($prd->problemStatement())->toContain('authenticate');
    });

    it('extracts target audience', function (): void {
        $content = <<<'MARKDOWN'
# Feature Name

## Target Audience
Enterprise users who need secure access to their accounts.

## Objectives
- Provide security
MARKDOWN;

        $prd = $this->parser->parse($content);

        expect($prd->targetAudience())->toContain('Enterprise users');
    });

    it('extracts constraints and assumptions', function (): void {
        $content = <<<'MARKDOWN'
# Feature Name

## Objectives
- Main objective

## Constraints
- Must support PHP 8.4+
- Must work with MySQL and PostgreSQL

## Assumptions
- Users have valid email addresses
- SSL is enabled
MARKDOWN;

        $prd = $this->parser->parse($content);

        expect($prd->constraints())->toHaveCount(2);
        expect($prd->assumptions())->toHaveCount(2);
    });

    it('extracts out of scope items', function (): void {
        $content = <<<'MARKDOWN'
# Feature Name

## Objectives
- Main objective

## Out of Scope
- Mobile app support
- Third-party integrations
- Analytics dashboard
MARKDOWN;

        $prd = $this->parser->parse($content);

        expect($prd->outOfScope())->toHaveCount(3);
        expect($prd->outOfScope())->toContain('Mobile app support');
    });

    it('extracts requirement IDs and priorities', function (): void {
        $content = <<<'MARKDOWN'
# Feature

## Objectives
- Main goal

## Requirements
- [REQ-1] Critical requirement (critical)
- [REQ-2] High priority item (high)
- REQ-3: Normal requirement
- Regular requirement without ID
MARKDOWN;

        $prd = $this->parser->parse($content);
        $requirements = $prd->requirements();

        expect($requirements)->toHaveCount(4);
        expect($requirements[0]['id'])->toBe('REQ-1');
        expect($requirements[0]['priority'])->toBe('critical');
        expect($requirements[1]['priority'])->toBe('high');
    });

    it('extracts user stories with standard format', function (): void {
        $content = <<<'MARKDOWN'
# Feature

## Objectives
- Goal

## User Stories
As a customer, I want to view my order history so that I can track my purchases.
As an admin, I want to manage users so that I can control access.
MARKDOWN;

        $prd = $this->parser->parse($content);
        $stories = $prd->userStories();

        expect($stories)->toHaveCount(2);
        expect($stories[0]['description'])->toContain('As a customer');
        expect($stories[1]['description'])->toContain('As an admin');
    });

    it('stores original content as metadata', function (): void {
        $content = "# Simple PRD\n\n## Objectives\n- Do something";

        $prd = $this->parser->parse($content);

        expect($prd->getMetadata('original_content'))->toBe($content);
        expect($prd->getMetadata('original_format'))->toBe('markdown');
        expect($prd->getMetadata('imported_at'))->not->toBeNull();
    });

    it('extracts title from file path when not in content', function (): void {
        $content = "No heading here\n\n## Objectives\n- Something";

        $prd = $this->parser->parse($content, '/path/to/my-awesome-feature-prd.md');

        expect($prd->title())->toBe('My Awesome Feature');
    });

    it('can parse plain text PRDs', function (): void {
        $content = <<<'TEXT'
User Dashboard Feature

Overview:
Create a dashboard for users to see their stats.

Goals:
- Show user statistics
- Display recent activity
- Provide quick actions

Requirements:
1. Dashboard loads in under 2 seconds
2. Shows last 10 activities
3. Includes logout button
TEXT;

        $prd = $this->parser->parse($content);

        expect($prd)->toBeInstanceOf(ProductRequirements::class);
        expect($prd->objectives())->not->toBeEmpty();
    });

    it('creates default objective when only requirements exist', function (): void {
        $content = <<<'MARKDOWN'
# Feature

## Requirements
- REQ-1: Implement login
- REQ-2: Implement logout
MARKDOWN;

        $prd = $this->parser->parse($content);

        // Should have at least one objective (auto-generated)
        expect($prd->objectives())->toHaveCount(1);
    });

    it('can validate empty content', function (): void {
        $errors = $this->parser->validateContent('');

        expect($errors)->toContain('Content is empty');
    });

    it('reports canParse true for any text', function (): void {
        expect($this->parser->canParse('any content'))->toBeTrue();
        expect($this->parser->canParse('# Markdown'))->toBeTrue();
        expect($this->parser->canParse('Plain text'))->toBeTrue();
    });

    it('handles success criteria extraction', function (): void {
        $content = <<<'MARKDOWN'
# Feature

## Objectives
- Main goal

## Success Criteria
- 95% uptime
- Response time under 200ms
- Zero critical bugs
MARKDOWN;

        $prd = $this->parser->parse($content);

        expect($prd->successCriteria())->toHaveCount(3);
    });

    it('returns correct type and extensions', function (): void {
        expect($this->parser->type())->toBe('prd');
        expect($this->parser->extensions())->toBe(['md', 'txt', 'markdown']);
    });
});
