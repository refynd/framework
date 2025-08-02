<?php

namespace Refynd\Console\Commands;

use Refynd\RateLimiter\RateLimiter;
use Refynd\RateLimiter\WebSocketRateLimiter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RateLimitCommand extends Command
{
    protected static $defaultName = 'rate-limit';
    protected static $defaultDescription = 'Manage framework rate limiting';

    protected function configure(): void
    {
        $this
            ->setDescription('Manage framework rate limiting')
            ->addArgument('component', InputArgument::REQUIRED, 'Component to manage (websocket, http, api)')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (stats, reset, test)')
            ->addOption('key', 'k', InputOption::VALUE_OPTIONAL, 'Specific key to target')
            ->addOption('max-requests', 'r', InputOption::VALUE_OPTIONAL, 'Maximum requests per time window', 60)
            ->addOption('time-window', 't', InputOption::VALUE_OPTIONAL, 'Time window in seconds', 60)
            ->setHelp('This command allows you to manage rate limiting for different framework components.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $component = $input->getArgument('component');
        $action = $input->getArgument('action');

        $rateLimiter = $this->getRateLimiter($component);
        
        if (!$rateLimiter) {
            $io->error("Unknown component: {$component}. Available: websocket, http, api");
            return Command::FAILURE;
        }

        switch ($action) {
            case 'stats':
                return $this->showStats($io, $component, $rateLimiter);
            
            case 'reset':
                return $this->resetRateLimit($io, $component, $rateLimiter, $input->getOption('key'));
            
            case 'test':
                return $this->testRateLimit($io, $component, $rateLimiter, $input);
            
            default:
                $io->error("Unknown action: {$action}. Available: stats, reset, test");
                return Command::FAILURE;
        }
    }

    private function getRateLimiter(string $component)
    {
        switch ($component) {
            case 'websocket':
                return new WebSocketRateLimiter();
            case 'http':
                return RateLimiter::for('http');
            case 'api':
                return RateLimiter::for('api');
            default:
                return null;
        }
    }

    private function showStats(SymfonyStyle $io, string $component, $rateLimiter): int
    {
        $io->title("Rate Limiter Statistics - {$component}");
        
        if ($component === 'websocket' && $rateLimiter instanceof WebSocketRateLimiter) {
            $stats = $rateLimiter->getServerStats();
            
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Component', $component],
                    ['Max Requests', $stats['max_requests']],
                    ['Time Window', $stats['time_window'] . ' seconds'],
                    ['Type', $stats['type']],
                ]
            );
        } else {
            $stats = $rateLimiter->getStatistics();
            
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Component', $component],
                    ['Cache Driver', $stats['cache_driver']],
                    ['Key Prefix', $stats['key_prefix']],
                    ['Current Time', date('Y-m-d H:i:s', $stats['current_time'])],
                ]
            );
        }

        $io->success('Statistics retrieved successfully');
        return Command::SUCCESS;
    }

    private function resetRateLimit(SymfonyStyle $io, string $component, $rateLimiter, ?string $key): int
    {
        if ($component === 'websocket' && $rateLimiter instanceof WebSocketRateLimiter) {
            if ($key) {
                $rateLimiter->resetClient($key);
                $io->success("Rate limit reset for WebSocket client: {$key}");
            } else {
                $io->warning('WebSocket rate limiter requires a specific client key');
                return Command::FAILURE;
            }
        } else {
            if ($key) {
                $rateLimiter->clear($key);
                $io->success("Rate limit reset for key: {$key}");
            } else {
                $io->warning('Framework rate limiter requires a specific key');
                return Command::FAILURE;
            }
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

    private function testRateLimit(SymfonyStyle $io, string $component, $rateLimiter, InputInterface $input): int
    {
        $maxRequests = (int) $input->getOption('max-requests');
        $timeWindow = (int) $input->getOption('time-window');

        $io->title("Rate Limiter Test - {$component}");
        
        if ($component === 'websocket' && $rateLimiter instanceof WebSocketRateLimiter) {
            return $this->testWebSocketRateLimit($io, $rateLimiter, $maxRequests, $timeWindow);
        } else {
            return $this->testGenericRateLimit($io, $rateLimiter, $maxRequests, $timeWindow);
        }
    }

    private function testWebSocketRateLimit(SymfonyStyle $io, WebSocketRateLimiter $rateLimiter, int $maxRequests, int $timeWindow): int
    {
        $testClient = 'test-client-' . uniqid();
        $io->note("Testing WebSocket rate limiter with client: {$testClient}");

        $allowed = 0;
        $blocked = 0;

        for ($i = 1; $i <= $maxRequests + 10; $i++) {
            if ($rateLimiter->isAllowed($testClient)) {
                $rateLimiter->hit($testClient, $timeWindow); // Manually hit since isAllowed doesn't increment
                $allowed++;
                $io->writeln("<info>[{$i}]</info> Request allowed (Total allowed: {$allowed})");
            } else {
                $blocked++;
                $remaining = $rateLimiter->getRemainingRequests($testClient);
                $blockedUntil = $rateLimiter->getBlockedUntil($testClient);
                
                $io->writeln("<error>[{$i}]</error> Request blocked (Total blocked: {$blocked}, Remaining: {$remaining}, Blocked until: " . date('H:i:s', $blockedUntil) . ")");
            }
        }

        $io->success("WebSocket test completed: {$allowed} allowed, {$blocked} blocked");
        
        // Cleanup test client
        $rateLimiter->resetClient($testClient);
        return Command::SUCCESS;
    }

    private function testGenericRateLimit(SymfonyStyle $io, RateLimiter $rateLimiter, int $maxRequests, int $timeWindow): int
    {
        $testKey = 'test-key-' . uniqid();
        $io->note("Testing generic rate limiter with key: {$testKey}");

        $allowed = 0;
        $blocked = 0;

        for ($i = 1; $i <= $maxRequests + 10; $i++) {
            if (!$rateLimiter->tooManyAttempts($testKey, $maxRequests)) {
                $rateLimiter->hit($testKey, $timeWindow);
                $allowed++;
                $remaining = $rateLimiter->retriesLeft($testKey, $maxRequests);
                $io->writeln("<info>[{$i}]</info> Request allowed (Total allowed: {$allowed}, Remaining: {$remaining})");
            } else {
                $blocked++;
                $availableIn = $rateLimiter->availableIn($testKey);
                
                $io->writeln("<error>[{$i}]</error> Request blocked (Total blocked: {$blocked}, Available in: {$availableIn}s)");
            }
        }

        $io->success("Generic test completed: {$allowed} allowed, {$blocked} blocked");
        
        // Cleanup test key
        $rateLimiter->clear($testKey);
        return Command::SUCCESS;
    }
}
