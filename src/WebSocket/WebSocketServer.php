<?php

namespace Refynd\WebSocket;

class WebSocketServer
{
    private mixed $socket;
    private array $clients = [];
    private array $channels = [];
    private string $host;
    private int $port;
    private bool $running = true;

    public function __construct(string $host = '127.0.0.1', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;
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

                    $message = $this->decode($data);
                    if ($message) {
                        $this->handleMessage($client, $message);
                    }
                }
            }
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

    public function sendToClient($client, string $message): void
    {
        $encodedMessage = $this->encode($message);
        @socket_write($client, $encodedMessage, strlen($encodedMessage));
    }

    public function joinChannel($client, string $channel): void
    {
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }
        
        $clientId = array_search($client, $this->clients);
        if (!in_array($clientId, $this->channels[$channel])) {
            $this->channels[$channel][] = $clientId;
        }
    }

    public function leaveChannel($client, string $channel): void
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

    private function handleMessage($client, array $message): void
    {
        switch ($message['type'] ?? '') {
            case 'join':
                $this->joinChannel($client, $message['channel']);
                break;
            case 'leave':
                $this->leaveChannel($client, $message['channel']);
                break;
            case 'message':
                $this->broadcast($message['data'], $message['channel'] ?? null);
                break;
        }
    }

    private function isClientInChannel($client, string $channel): bool
    {
        if (!isset($this->channels[$channel])) {
            return false;
        }
        
        $clientId = array_search($client, $this->clients);
        return in_array($clientId, $this->channels[$channel]);
    }

    private function performHandshake($client): void
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
