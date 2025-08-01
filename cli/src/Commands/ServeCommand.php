<?php

namespace Refynd\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Start the development server
 */
class ServeCommand extends Command
{
    protected static $defaultName = 'serve';
    protected static $defaultDescription = 'Start the development server';

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', 'localhost')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve the application on', '8000')
            ->setDescription('Start the development server')
            ->setHelp('This command starts the built-in PHP development server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $host = $input->getOption('host');
        $port = $input->getOption('port');

        $publicPath = getcwd() . '/public';
        
        if (!is_dir($publicPath)) {
            $io->error("Public directory not found. Make sure you're in a Refynd project root.");
            return Command::FAILURE;
        }

        $address = "{$host}:{$port}";
        $io->title("🚀 Starting Refynd development server");
        $io->text("Server: http://{$address}");
        $io->text("Document root: {$publicPath}");
        $io->newLine();
        $io->note("Press Ctrl+C to stop the server");

        $process = new Process([
            'php',
            '-S',
            $address,
            '-t',
            $publicPath,
        ]);

        $process->setTimeout(null);

        try {
            $process->run(function ($type, $buffer) use ($io) {
                $io->write($buffer);
            });
        } catch (\Exception $e) {
            $io->error("Server failed to start: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
