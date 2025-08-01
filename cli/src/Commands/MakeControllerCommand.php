<?php

namespace Refynd\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Refynd\Cli\Generators\ControllerGenerator;

/**
 * Generate a new controller class
 */
class MakeControllerCommand extends Command
{
    protected static $defaultName = 'make:controller';
    protected static $defaultDescription = 'Create a new controller class';

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the controller')
            ->addOption('resource', 'r', InputOption::VALUE_NONE, 'Generate a resource controller')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Generate an API controller')
            ->setDescription('Create a new controller class')
            ->setHelp('This command creates a new controller class in the app/Http/Controllers directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $resource = $input->getOption('resource');
        $api = $input->getOption('api');

        try {
            $generator = new ControllerGenerator();
            $filePath = $generator->generate($name, [
                'resource' => $resource,
                'api' => $api,
            ]);

            $io->success("Controller created successfully: {$filePath}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to create controller: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
