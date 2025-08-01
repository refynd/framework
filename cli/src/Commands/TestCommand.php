<?php

namespace Refynd\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Run the application tests
 */
class TestCommand extends Command
{
    protected static $defaultName = 'test';
    protected static $defaultDescription = 'Run the application tests';

    protected function configure(): void
    {
        $this
            ->addOption('coverage', 'c', InputOption::VALUE_NONE, 'Generate code coverage report')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Filter which tests to run')
            ->setDescription('Run the application tests')
            ->setHelp('This command runs PHPUnit tests with enhanced output formatting.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $coverage = $input->getOption('coverage');
        $filter = $input->getOption('filter');

        if (!file_exists(getcwd() . '/phpunit.xml') && !file_exists(getcwd() . '/phpunit.xml.dist')) {
            $io->error("PHPUnit configuration file not found. Make sure you're in a Refynd project root.");
            return Command::FAILURE;
        }

        $io->title("🧪 Running Refynd Tests");

        $command = ['vendor/bin/phpunit'];

        if ($coverage) {
            $command[] = '--coverage-html=coverage';
            $io->text("📊 Code coverage will be generated in ./coverage/");
        }

        if ($filter) {
            $command[] = '--filter=' . $filter;
            $io->text("🔍 Filtering tests: {$filter}");
        }

        $process = new Process($command, getcwd());
        $process->setTimeout(300); // 5 minutes timeout

        try {
            $process->run(function ($type, $buffer) use ($io) {
                $io->write($buffer);
            });

            if ($process->isSuccessful()) {
                $io->success("✅ All tests passed!");
                return Command::SUCCESS;
            } else {
                $io->error("❌ Some tests failed.");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Test execution failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
