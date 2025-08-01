<?php

namespace Refynd\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Refynd\Cli\Generators\ModelGenerator;

/**
 * Generate a new model class
 */
class MakeModelCommand extends Command
{
    protected static $defaultName = 'make:model';
    protected static $defaultDescription = 'Create a new model class';

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the model')
            ->setDescription('Create a new model class')
            ->setHelp('This command creates a new model class in the app/Models directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        try {
            $generator = new ModelGenerator();
            $filePath = $generator->generate($name);

            $io->success("Model created successfully: {$filePath}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to create model: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
