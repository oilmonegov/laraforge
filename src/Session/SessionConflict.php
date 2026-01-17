<?php

declare(strict_types=1);

namespace LaraForge\Session;

class SessionConflict
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly Session $conflictingSession,
        public readonly string $suggestion,
    ) {}

    /**
     * Check if this is a blocking conflict.
     */
    public function isBlocking(): bool
    {
        return $this->type === 'same_branch';
    }
}
