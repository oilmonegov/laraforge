<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\LaraForge;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

abstract class Command extends SymfonyCommand
{
    public function __construct(
        protected readonly LaraForge $laraforge,
    ) {
        parent::__construct();
    }
}
