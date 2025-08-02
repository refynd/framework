<?php

namespace Refynd\RateLimiter;

use Refynd\Cache\CacheInterface;

class WebSocketRateLimiter extends RateLimiter
{
    private int $maxRequests;
    private int $decaySeconds;

    public function __construct(?CacheInterface $cache = null, int $maxRequests = 60, int $decaySeconds = 60)
    {
        parent::__construct($cache, 'websocket');
        $this->maxRequests = $maxRequests;
        $this->decaySeconds = $decaySeconds;
    }

    public function isAllowed(mixed $client): bool
    {
        $key = $this->getClientKey($client);
        return !$this->tooManyAttempts($key, $this->maxRequests);
    }

    public function checkClient(mixed $client): void
    {
        $key = $this->getClientKey($client);

        if ($this->tooManyAttempts($key, $this->maxRequests)) {
            $limitInfo = $this->getLimitInfo($key, $this->maxRequests, $this->decaySeconds);
            throw new RateLimitExceededException(
                'WebSocket rate limit exceeded',
                $this->availableIn($key),
                $limitInfo
            );
        }

        $this->hit($key, $this->decaySeconds);
    }

    public function getRemainingRequests(mixed $client): int
    {
        $key = $this->getClientKey($client);
        return $this->retriesLeft($key, $this->maxRequests);
    }

    public function getBlockedUntil(mixed $client): int
    {
        $key = $this->getClientKey($client);
        $availableIn = $this->availableIn($key);
        return $availableIn > 0 ? time() + $availableIn : 0;
    }

    public function resetClient(mixed $client): void
    {
        $key = $this->getClientKey($client);
        $this->clear($key);
    }

    public function getClientStats(mixed $client): array
    {
        $key = $this->getClientKey($client);
        return $this->getLimitInfo($key, $this->maxRequests, $this->decaySeconds);
    }

    public function getServerStats(): array
    {
        return ['max_requests' => $this->maxRequests,
            'time_window' => $this->decaySeconds,
            'type' => 'websocket',];
    }

    private function getClientKey(mixed $client): string
    {
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
}
