<?php

namespace Refynd\Console\Commands;

use Refynd\WebSocket\WebSocketServer;
use Refynd\RateLimiter\WebSocketRateLimiter;
use Refynd\Container\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WebSocketServerCommand extends Command
{
    private Container $container; // @phpstan-ignore-line Container reserved for future dependency injection

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
             ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'The port to bind to', 8080)
             ->addOption('max-requests', null, InputOption::VALUE_OPTIONAL, 'Maximum requests per time window', 60)
             ->addOption('time-window', null, InputOption::VALUE_OPTIONAL, 'Time window in seconds', 60)
             ->addOption('block-duration', null, InputOption::VALUE_OPTIONAL, 'Block duration in seconds', 300)
             ->addOption('disable-rate-limit', null, InputOption::VALUE_NONE, 'Disable rate limiting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = (int) $input->getOption('port');
        
        // Rate limiting configuration
        $rateLimiter = null;
        if (!$input->getOption('disable-rate-limit')) {
            $maxRequests = (int) $input->getOption('max-requests');
            $timeWindow = (int) $input->getOption('time-window');
            $blockDuration = (int) $input->getOption('block-duration');
            
            $rateLimiter = new WebSocketRateLimiter(null, $maxRequests, $timeWindow);
            
            $output->writeln("<info>Rate limiting enabled:</info>");
            $output->writeln("  - Max requests: {$maxRequests} per {$timeWindow} seconds");
            $output->writeln("  - Block duration: {$blockDuration} seconds");
        } else {
            $output->writeln("<comment>Rate limiting disabled</comment>");
        }
        
        $output->writeln("<info>Starting WebSocket server on {$host}:{$port}</info>");
        $output->writeln("<comment>Press Ctrl+C to stop</comment>");
        
        $server = new WebSocketServer($host, $port, $rateLimiter);
        
        // Handle graceful shutdown
        pcntl_signal(SIGTERM, function() use ($server) {
            $server->stop();
        });
        pcntl_signal(SIGINT, function() use ($server) {
            $server->stop();
        });
        
        $server->start();
        
        return Command::SUCCESS;
    }
}
