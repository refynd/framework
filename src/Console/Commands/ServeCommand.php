<?php

namespace Refynd\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ServeCommand - Start the development server
 */
class ServeCommand extends Command
{
    protected static string $defaultName = 'serve';
    protected static string $defaultDescription = 'Start the development server';

    protected function configure(): void
    {
        $this->setName('serve:app')
             ->setDescription('Start the Refynd development server');

        $this->addOption(
            'host',
            null,
            InputOption::VALUE_OPTIONAL,
            'The host to serve on',
            'localhost'
        );

        $this->addOption(
            'port',
            'p',
            InputOption::VALUE_OPTIONAL,
            'The port to serve on',
            '8000'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');

        $output->writeln(['',
            '🔨 <fg = blue > Starting Refynd Development Server</fg = blue>',
            '<fg = yellow>═══════════════════════════════════════</fg = yellow>',
            '',
            "Server running at: <fg = green > http://{$host}:{$port}</fg = green>",
            '',
            '<comment > Press Ctrl+C to stop the server</comment>',
            '',]);

        // Get the public directory path
        $publicPath = getcwd() . '/public';

        if (!is_dir($publicPath)) {
            $output->writeln('<error > Public directory not found. Please run this command from your Refynd project root.</error>');
            return Command::FAILURE;
        }

        // Start the PHP development server
        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($publicPath)
        );

        // Execute the server command
        $process = proc_open(
            $command,
            [0 => STDIN,
                1 => STDOUT,
                2 => STDERR,],
            $pipes
        );

        if (is_resource($process)) {
            proc_close($process);
        }

        return Command::SUCCESS;
    }
}
