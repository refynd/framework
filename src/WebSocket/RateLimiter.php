<?php

namespace Refynd\WebSocket;

class RateLimiter
{
    private array $clients = [];
    private int $maxRequests;
    private int $timeWindow;
    private int $blockDuration;

    public function __construct(int $maxRequests = 60, int $timeWindow = 60, int $blockDuration = 300)
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->blockDuration = $blockDuration;
    }

    public function isAllowed($client): bool
    {
        $clientId = $this->getClientId($client);
        $now = time();

        // Initialize client data if not exists
        if (!isset($this->clients[$clientId])) {
            $this->clients[$clientId] = [
                'requests' => [],
                'blocked_until' => 0
            ];
        }

        $clientData = &$this->clients[$clientId];

        // Check if client is currently blocked
        if ($clientData['blocked_until'] > $now) {
            return false;
        }

        // Clean old requests outside the time window
        $clientData['requests'] = array_filter(
            $clientData['requests'],
            fn($timestamp) => $timestamp > ($now - $this->timeWindow)
        );

        // Check if client has exceeded rate limit
        if (count($clientData['requests']) >= $this->maxRequests) {
            // Block the client
            $clientData['blocked_until'] = $now + $this->blockDuration;
            return false;
        }

        // Record this request
        $clientData['requests'][] = $now;
        return true;
    }

    public function getRemainingRequests($client): int
    {
        $clientId = $this->getClientId($client);
        
        if (!isset($this->clients[$clientId])) {
            return $this->maxRequests;
        }

        $now = time();
        $clientData = $this->clients[$clientId];

        // Clean old requests
        $recentRequests = array_filter(
            $clientData['requests'],
            fn($timestamp) => $timestamp > ($now - $this->timeWindow)
        );

        return max(0, $this->maxRequests - count($recentRequests));
    }

    public function getBlockedUntil($client): int
    {
        $clientId = $this->getClientId($client);
        
        if (!isset($this->clients[$clientId])) {
            return 0;
        }

        return $this->clients[$clientId]['blocked_until'] ?? 0;
    }

    public function reset($client = null): void
    {
        if ($client === null) {
            $this->clients = [];
        } else {
            $clientId = $this->getClientId($client);
            unset($this->clients[$clientId]);
        }
    }

    public function cleanup(): void
    {
        $now = time();
        
        foreach ($this->clients as $clientId => $clientData) {
            // Remove clients that are no longer blocked and have no recent requests
            $hasRecentRequests = !empty(array_filter(
                $clientData['requests'],
                fn($timestamp) => $timestamp > ($now - $this->timeWindow)
            ));
            
            $isBlocked = ($clientData['blocked_until'] ?? 0) > $now;
            
            if (!$hasRecentRequests && !$isBlocked) {
                unset($this->clients[$clientId]);
            }
        }
    }

    private function getClientId(mixed $client): string
    {
        // Get client socket resource ID or IP address
        if (is_resource($client) || $client instanceof \Socket) {
            $address = '';
            $port = 0;
            $peerName = @socket_getpeername($client, $address, $port);
            return $peerName ? "{$address}:{$port}" : (string)$client;
        }
        
        if (is_object($client)) {
            return spl_object_hash($client);
        }
        
        return (string)$client;
    }

    public function getStats(): array
    {
        $now = time();
        $totalClients = count($this->clients);
        $blockedClients = 0;
        $activeClients = 0;

        foreach ($this->clients as $clientData) {
            if (($clientData['blocked_until'] ?? 0) > $now) {
                $blockedClients++;
            }
            
            $recentRequests = array_filter(
                $clientData['requests'],
                fn($timestamp) => $timestamp > ($now - $this->timeWindow)
            );
            
            if (!empty($recentRequests)) {
                $activeClients++;
            }
        }

        return [
            'total_clients' => $totalClients,
            'active_clients' => $activeClients,
            'blocked_clients' => $blockedClients,
            'max_requests' => $this->maxRequests,
            'time_window' => $this->timeWindow,
            'block_duration' => $this->blockDuration
        ];
    }
}
