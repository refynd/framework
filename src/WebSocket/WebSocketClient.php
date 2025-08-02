<?php

namespace Refynd\WebSocket;

class WebSocketClient
{
    private string $url;
    private mixed $socket;
    private bool $connected = false;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function connect(): bool
    {
        $urlParts = parse_url($this->url);
        $host = $urlParts['host'];
        $port = $urlParts['port'] ?? 80;
        $path = $urlParts['path'] ?? '/';
        
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $result = socket_connect($this->socket, $host, $port);
        
        if (!$result) {
            return false;
        }
        
        $key = base64_encode(random_bytes(16));
        
        $headers = [
            "GET {$path} HTTP/1.1",
            "Host: {$host}:{$port}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: {$key}",
            "Sec-WebSocket-Version: 13",
            "",
            ""
        ];
        
        $request = implode("\r\n", $headers);
        socket_write($this->socket, $request, strlen($request));
        
        $response = socket_read($this->socket, 2048);
        
        if (strpos($response, '101 Switching Protocols') !== false) {
            $this->connected = true;
            return true;
        }
        
        return false;
    }

    public function send(array $message): bool
    {
        if (!$this->connected) {
            return false;
        }
        
        $payload = json_encode($message);
        $encoded = $this->encode($payload);
        
        return socket_write($this->socket, $encoded, strlen($encoded)) !== false;
    }

    public function receive(): ?array
    {
        if (!$this->connected) {
            return null;
        }
        
        $data = socket_read($this->socket, 1024);
        if ($data === false) {
            return null;
        }
        
        $decoded = $this->decode($data);
        return $decoded ? json_decode($decoded, true) : null;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            socket_close($this->socket);
            $this->connected = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    private function encode(string $text): string
    {
        $length = strlen($text);
        $mask = random_bytes(4);
        
        if ($length < 126) {
            $header = chr(129) . chr($length | 128) . $mask;
        } elseif ($length < 65536) {
            $header = chr(129) . chr(126 | 128) . pack('n', $length) . $mask;
        } else {
            $header = chr(129) . chr(127 | 128) . pack('J', $length) . $mask;
        }
        
        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $text[$i] ^ $mask[$i % 4];
        }
        
        return $header . $masked;
    }

    private function decode(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }
        
        $length = ord($data[1]) & 127;
        
        if ($length == 126) {
            $payload = substr($data, 4);
        } elseif ($length == 127) {
            $payload = substr($data, 10);
        } else {
            $payload = substr($data, 2);
        }
        
        return $payload;
    }
}
