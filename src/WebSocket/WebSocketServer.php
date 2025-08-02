<?php

namespace Refynd\WebSocket;

use Refynd\RateLimiter\WebSocketRateLimiter;
use Refynd\RateLimiter\RateLimitExceededException;

class WebSocketServer
{
    private mixed $socket;
    private array $clients = [];
    private array $channels = [];
    private string $host;
    private int $port;
    private bool $running = true;
    private WebSocketRateLimiter $rateLimiter;

    public function __construct(string $host = '127.0.0.1', int $port = 8080, ?WebSocketRateLimiter $rateLimiter = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->rateLimiter = $rateLimiter ?? new WebSocketRateLimiter();
    }

    public function start(): void
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $this->host, $this->port);
        socket_listen($this->socket, 20);

        echo "WebSocket server started on {$this->host}:{$this->port}\n";

        while ($this->running) {
            $sockets = array_merge([$this->socket], $this->clients);
            $write = null;
            $except = null;

            if (socket_select($sockets, $write, $except, 0, 10000) < 1) {
                continue;
            }

            if (in_array($this->socket, $sockets)) {
                $newClient = socket_accept($this->socket);
                $this->clients[] = $newClient;
                $this->performHandshake($newClient);
                echo "New client connected\n";
            }

            foreach ($this->clients as $key => $client) {
                if (in_array($client, $sockets)) {
                    $data = @socket_read($client, 1024, PHP_NORMAL_READ);
                    
                    if ($data === false || $data === '') {
                        $this->disconnect($key);
                        continue;
                    }

                    // Check rate limit before processing message
                    try {
                        $this->rateLimiter->checkClient($client);
                    } catch (RateLimitExceededException $e) {
                        $this->sendRateLimitError($client, $e);
                        continue;
                    }

                    $message = $this->decode($data);
                    if ($message) {
                        $this->handleMessage($client, $message);
                    }
                }
            }
            
            // Cleanup rate limiter periodically - removed as it's handled automatically
            // static $lastCleanup = 0;
            // if (time() - $lastCleanup > 300) {
            //     $lastCleanup = time();
            // }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function broadcast(string $message, ?string $channel = null): void
    {
        $encodedMessage = $this->encode($message);
        
        foreach ($this->clients as $client) {
            if ($channel === null || $this->isClientInChannel($client, $channel)) {
                @socket_write($client, $encodedMessage, strlen($encodedMessage));
            }
        }
    }

    public function sendToClient(mixed $client, string $message): void
    {
        $encodedMessage = $this->encode($message);
        @socket_write($client, $encodedMessage, strlen($encodedMessage));
    }

    public function sendRateLimitError(mixed $client, ?RateLimitExceededException $exception = null): void
    {
        $remainingRequests = $this->rateLimiter->getRemainingRequests($client);
        $blockedUntil = $this->rateLimiter->getBlockedUntil($client);
        
        $error = [
            'type' => 'rate_limit_error',
            'message' => $exception ? $exception->getMessage() : 'Rate limit exceeded',
            'remaining_requests' => $remainingRequests,
            'blocked_until' => $blockedUntil > 0 ? date('c', $blockedUntil) : null
        ];
        
        if ($exception) {
            $error['limit_info'] = $exception->getLimitInfo();
        }
        
        $this->sendToClient($client, json_encode($error));
    }

    public function getRateLimiterStats(): array
    {
        return $this->rateLimiter->getServerStats();
    }

    public function resetRateLimit(mixed $client = null): void
    {
        if ($client) {
            $this->rateLimiter->resetClient($client);
        }
        // Note: WebSocketRateLimiter doesn't support resetting all clients
        // This would need to be implemented if required
    }

    public function joinChannel(mixed $client, string $channel): void
    {
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }
        
        $clientId = array_search($client, $this->clients);
        if (!in_array($clientId, $this->channels[$channel])) {
            $this->channels[$channel][] = $clientId;
        }
    }

    public function leaveChannel(mixed $client, string $channel): void
    {
        if (!isset($this->channels[$channel])) {
            return;
        }
        
        $clientId = array_search($client, $this->clients);
        $key = array_search($clientId, $this->channels[$channel]);
        
        if ($key !== false) {
            unset($this->channels[$channel][$key]);
        }
    }

    private function handleMessage(mixed $client, array $message): void
    {
        switch ($message['type'] ?? '') {
            case 'join':
                $this->joinChannel($client, $message['channel']);
                $this->sendStatusResponse($client, 'joined', $message['channel']);
                break;
            case 'leave':
                $this->leaveChannel($client, $message['channel']);
                $this->sendStatusResponse($client, 'left', $message['channel']);
                break;
            case 'message':
                $this->broadcast($message['data'], $message['channel'] ?? null);
                break;
            case 'stats':
                $this->sendStatsResponse($client);
                break;
        }
    }

    private function sendStatusResponse(mixed $client, string $action, string $channel): void
    {
        $response = [
            'type' => 'status',
            'action' => $action,
            'channel' => $channel,
            'rate_limit' => [
                'remaining_requests' => $this->rateLimiter->getRemainingRequests($client)
            ]
        ];
        
        $this->sendToClient($client, json_encode($response));
    }

    private function sendStatsResponse(mixed $client): void
    {
        $stats = [
            'type' => 'stats',
            'server' => [
                'connected_clients' => count($this->clients),
                'channels' => count($this->channels)
            ],
            'rate_limiter' => $this->rateLimiter->getServerStats()
        ];
        
        $this->sendToClient($client, json_encode($stats));
    }

    private function isClientInChannel(mixed $client, string $channel): bool
    {
        if (!isset($this->channels[$channel])) {
            return false;
        }
        
        $clientId = array_search($client, $this->clients);
        return in_array($clientId, $this->channels[$channel]);
    }

    private function performHandshake(mixed $client): void
    {
        $request = socket_read($client, 5000);
        preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
        
        if (empty($matches[1])) {
            return;
        }
        
        $key = $matches[1];
        $acceptKey = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";
        
        socket_write($client, $response, strlen($response));
    }

    private function encode(string $text): string
    {
        $length = strlen($text);
        
        if ($length < 126) {
            return chr(129) . chr($length) . $text;
        } elseif ($length < 65536) {
            return chr(129) . chr(126) . pack('n', $length) . $text;
        } else {
            return chr(129) . chr(127) . pack('J', $length) . $text;
        }
    }

    private function decode(string $data): ?array
    {
        if (strlen($data) < 2) {
            return null;
        }
        
        $length = ord($data[1]) & 127;
        
        if ($length == 126) {
            $masks = substr($data, 4, 4);
            $payload = substr($data, 8);
        } elseif ($length == 127) {
            $masks = substr($data, 10, 4);
            $payload = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $payload = substr($data, 6);
        }
        
        $text = '';
        for ($i = 0; $i < strlen($payload); ++$i) {
            $text .= $payload[$i] ^ $masks[$i % 4];
        }
        
        return json_decode($text, true);
    }

    private function disconnect(int $key): void
    {
        $client = $this->clients[$key];
        
        // Clean up rate limiter data for this client
        $this->rateLimiter->resetClient($client);
        
        socket_close($client);
        unset($this->clients[$key]);
        
        // Remove from all channels
        foreach ($this->channels as $channel => $clientIds) {
            $clientKey = array_search($key, $clientIds);
            if ($clientKey !== false) {
                unset($this->channels[$channel][$clientKey]);
            }
        }
        
        echo "Client disconnected\n";
    }
}
