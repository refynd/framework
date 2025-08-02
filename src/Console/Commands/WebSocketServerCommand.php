<?php

namespace Refynd\Console\Commands;

use Refynd\WebSocket\WebSocketServer;
use Refynd\Container\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WebSocketServerCommand extends Command
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('websocket:serve')
             ->setDescription('Start the WebSocket server')
             ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host to bind to', '127.0.0.1')
             ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'The port to bind to', 8080);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = (int) $input->getOption('port');
        
        $output->writeln("<info>Starting WebSocket server on {$host}:{$port}</info>");
        $output->writeln("<comment>Press Ctrl+C to stop</comment>");
        
        $server = new WebSocketServer($host, $port);
        
        // Handle graceful shutdown
        pcntl_signal(SIGTERM, function() {
            exit(0);
        });
        pcntl_signal(SIGINT, function() {
            exit(0);
        });
        
        $server->start();
        
        return Command::SUCCESS;
    }
}
