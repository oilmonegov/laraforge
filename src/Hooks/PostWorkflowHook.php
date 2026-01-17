<?php

declare(strict_types=1);

namespace LaraForge\Hooks;

abstract class PostWorkflowHook extends Hook
{
    public function type(): string
    {
        return 'post-workflow';
    }
}
