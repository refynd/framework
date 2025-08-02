<?php

namespace Refynd\Console;

use Refynd\Container\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * ConsoleKernel - Handles CLI Commands
 * 
 * Manages the console application and processes CLI commands
 * through the Smith command-line interface.
 */
class ConsoleKernel
{
    protected Container $container;
    protected Application $console;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->console = new Application('Refynd Smith', '1.0.0');
        $this->console->setAutoExit(false);
        
        $this->loadCommands();
    }

    /**
     * Handle console input and return exit code
     */
    public function handle(): int
    {
        $input = new ArgvInput();
        $output = new ConsoleOutput();

        return $this->console->run($input, $output);
    }

    /**
     * Load available commands
     */
    protected function loadCommands(): void
    {
        // Add default commands
        $this->console->add(new Commands\WelcomeCommand());
        $this->console->add(new Commands\ServeCommand());
        
        // Additional commands will be loaded from modules
    }

    /**
     * Add a command to the console application
     */
    public function addCommand(Command $command): void
    {
        $this->console->add($command);
    }

    /**
     * Get the console application
     */
    public function getConsole(): Application
    {
        return $this->console;
    }
}
