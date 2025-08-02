<?php

namespace Refynd\Console\Commands;

use Refynd\WebSocket\RateLimiter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RateLimitCommand extends Command
{
    protected static $defaultName = 'websocket:rate-limit';
    protected static $defaultDescription = 'Manage WebSocket rate limiting';

    protected function configure(): void
    {
        $this
            ->setDescription('Manage WebSocket rate limiting')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (stats, reset, config)')
            ->addOption('client', 'c', InputOption::VALUE_OPTIONAL, 'Specific client to target')
            ->addOption('max-requests', 'r', InputOption::VALUE_OPTIONAL, 'Maximum requests per time window', 60)
            ->addOption('time-window', 't', InputOption::VALUE_OPTIONAL, 'Time window in seconds', 60)
            ->addOption('block-duration', 'b', InputOption::VALUE_OPTIONAL, 'Block duration in seconds', 300)
            ->setHelp('This command allows you to manage WebSocket rate limiting settings and view statistics.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        switch ($action) {
            case 'stats':
                return $this->showStats($io);
            
            case 'reset':
                return $this->resetRateLimit($io, $input->getOption('client'));
            
            case 'config':
                return $this->showConfig($io, $input);
            
            case 'test':
                return $this->testRateLimit($io, $input);
            
            default:
                $io->error("Unknown action: {$action}");
                return Command::FAILURE;
        }
    }

    private function showStats(SymfonyStyle $io): int
    {
        $rateLimiter = new RateLimiter();
        $stats = $rateLimiter->getStats();

        $io->title('WebSocket Rate Limiter Statistics');
        
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Clients', $stats['total_clients']],
                ['Active Clients', $stats['active_clients']],
                ['Blocked Clients', $stats['blocked_clients']],
                ['Max Requests', $stats['max_requests']],
                ['Time Window', $stats['time_window'] . ' seconds'],
                ['Block Duration', $stats['block_duration'] . ' seconds']
            ]
        );

        if ($stats['blocked_clients'] > 0) {
            $io->warning("There are {$stats['blocked_clients']} blocked clients");
        } else {
            $io->success('No clients are currently blocked');
        }

        return Command::SUCCESS;
    }

    private function resetRateLimit(SymfonyStyle $io, ?string $client): int
    {
        $rateLimiter = new RateLimiter();
        
        if ($client) {
            $rateLimiter->reset($client);
            $io->success("Rate limit reset for client: {$client}");
        } else {
            $rateLimiter->reset();
            $io->success('Rate limit reset for all clients');
        }

        return Command::SUCCESS;
    }

    private function showConfig(SymfonyStyle $io, InputInterface $input): int
    {
        $maxRequests = (int) $input->getOption('max-requests');
        $timeWindow = (int) $input->getOption('time-window');
        $blockDuration = (int) $input->getOption('block-duration');

        $io->title('Rate Limiter Configuration');
        
        $io->table(
            ['Setting', 'Value', 'Description'],
            [
                ['Max Requests', $maxRequests, 'Maximum requests per time window'],
                ['Time Window', $timeWindow . ' seconds', 'Duration for request counting'],
                ['Block Duration', $blockDuration . ' seconds', 'How long clients are blocked when limit exceeded'],
                ['Rate', ($maxRequests / $timeWindow) . ' req/sec', 'Average requests per second allowed']
            ]
        );

        $io->note([
            'To apply these settings, restart the WebSocket server with the new configuration.',
            'Example: Use these values in your WebSocket server constructor:',
            "new RateLimiter({$maxRequests}, {$timeWindow}, {$blockDuration})"
        ]);

        return Command::SUCCESS;
    }

    private function testRateLimit(SymfonyStyle $io, InputInterface $input): int
    {
        $maxRequests = (int) $input->getOption('max-requests');
        $timeWindow = (int) $input->getOption('time-window');
        $blockDuration = (int) $input->getOption('block-duration');

        $rateLimiter = new RateLimiter($maxRequests, $timeWindow, $blockDuration);
        $testClient = 'test-client-' . uniqid();

        $io->title('Rate Limiter Test');
        $io->note("Testing with client: {$testClient}");

        $allowed = 0;
        $blocked = 0;

        for ($i = 1; $i <= $maxRequests + 10; $i++) {
            if ($rateLimiter->isAllowed($testClient)) {
                $allowed++;
                $io->writeln("<info>[{$i}]</info> Request allowed (Total allowed: {$allowed})");
            } else {
                $blocked++;
                $remaining = $rateLimiter->getRemainingRequests($testClient);
                $blockedUntil = $rateLimiter->getBlockedUntil($testClient);
                
                $io->writeln("<error>[{$i}]</error> Request blocked (Total blocked: {$blocked}, Remaining: {$remaining}, Blocked until: " . date('H:i:s', $blockedUntil) . ")");
            }
        }

        $io->success("Test completed: {$allowed} allowed, {$blocked} blocked");
        
        // Cleanup test client
        $rateLimiter->reset($testClient);

        return Command::SUCCESS;
    }
}
