<?php

declare(strict_types=1);

namespace LaraForge;

use LaraForge\Commands\GenerateCommand;
use LaraForge\Commands\InitCommand;
use LaraForge\Commands\ListGeneratorsCommand;
use LaraForge\Commands\VersionCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Application extends ConsoleApplication
{
    private LaraForge $laraforge;

    public function __construct()
    {
        parent::__construct('LaraForge', LaraForge::VERSION);

        $this->laraforge = new LaraForge;
        $this->registerCommands();
    }

    public function getLaraForge(): LaraForge
    {
        return $this->laraforge;
    }

    private function registerCommands(): void
    {
        // Core commands
        $this->add(new InitCommand($this->laraforge));
        $this->add(new GenerateCommand($this->laraforge));
        $this->add(new ListGeneratorsCommand($this->laraforge));
        $this->add(new VersionCommand($this->laraforge));

        // Register adapter commands
        foreach ($this->laraforge->adapters() as $adapter) {
            foreach ($adapter->commands() as $commandClass) {
                $this->add(new $commandClass($this->laraforge));
            }
        }

        // Register plugin commands
        foreach ($this->laraforge->plugins() as $plugin) {
            foreach ($plugin->commands() as $commandClass) {
                $this->add(new $commandClass($this->laraforge));
            }
        }
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        // Boot LaraForge before running
        $this->laraforge->boot();

        return parent::run($input, $output);
    }
}
