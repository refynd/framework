<?php

namespace Refynd\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Refynd\Cli\Generators\MiddlewareGenerator;

/**
 * Generate a new middleware class
 */
class MakeMiddlewareCommand extends Command
{
    protected static $defaultName = 'make:middleware';
    protected static $defaultDescription = 'Create a new middleware class';

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the middleware')
            ->setDescription('Create a new middleware class')
            ->setHelp('This command creates a new middleware class in the app/Http/Middleware directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        try {
            $generator = new MiddlewareGenerator();
            $filePath = $generator->generate($name);

            $io->success("Middleware created successfully: {$filePath}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to create middleware: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
