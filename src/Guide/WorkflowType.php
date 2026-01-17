<?php

declare(strict_types=1);

namespace LaraForge\Guide;

enum WorkflowType: string
{
    case ONBOARDING = 'onboarding';
    case FEATURE = 'feature';
    case BUGFIX = 'bugfix';
    case REFACTOR = 'refactor';
    case HOTFIX = 'hotfix';

    public function label(): string
    {
        return match ($this) {
            self::ONBOARDING => 'Project Setup',
            self::FEATURE => 'New Feature',
            self::BUGFIX => 'Bug Fix',
            self::REFACTOR => 'Refactoring',
            self::HOTFIX => 'Hotfix (Urgent)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ONBOARDING => 'Initial project setup and configuration',
            self::FEATURE => 'Full feature development: PRD → FRD → Branch → Implement → Test → PR',
            self::BUGFIX => 'Bug fix workflow: Branch → Fix → Test → PR',
            self::REFACTOR => 'Code refactoring: Branch → Refactor → Test → PR',
            self::HOTFIX => 'Urgent fix: Branch from main → Fix → Test → PR → Merge immediately',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ONBOARDING => '🚀',
            self::FEATURE => '✨',
            self::BUGFIX => '🐛',
            self::REFACTOR => '♻️',
            self::HOTFIX => '🔥',
        };
    }

    /**
     * @return array<self>
     */
    public static function forExistingProject(): array
    {
        return [
            self::FEATURE,
            self::BUGFIX,
            self::REFACTOR,
            self::HOTFIX,
        ];
    }
}
