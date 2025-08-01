<?php

namespace Refynd\Cli;

use Symfony\Component\Console\Application as ConsoleApplication;
use Refynd\Cli\Commands\NewCommand;
use Refynd\Cli\Commands\MakeControllerCommand;
use Refynd\Cli\Commands\MakeModelCommand;
use Refynd\Cli\Commands\MakeMiddlewareCommand;
use Refynd\Cli\Commands\ServeCommand;
use Refynd\Cli\Commands\TestCommand;

/**
 * Refynd CLI Application
 * 
 * Main entry point for the Refynd command line interface
 */
class Application extends ConsoleApplication
{
    public function __construct()
    {
        parent::__construct('Refynd CLI', '1.0.0');

        $this->addCommands([
            new NewCommand(),
            new MakeControllerCommand(),
            new MakeModelCommand(),
            new MakeMiddlewareCommand(),
            new ServeCommand(),
            new TestCommand(),
        ]);
    }
}
