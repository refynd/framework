<?php

namespace Refynd\Mail\Drivers;

use Refynd\Mail\MailInterface;
use Refynd\Mail\Mailable;

/**
 * SmtpDriver - SMTP mail implementation
 *
 * Provides SMTP email sending functionality with authentication
 * and security options.
 */
class SmtpDriver implements MailInterface
{
    protected array $config;
    protected $connection = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(['host' => 'localhost',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'auth' => true,
            'timeout' => 30,
            'from_email' => '',
            'from_name' => '',], $config);
    }

    /**
     * Send an email via SMTP
     */
    public function send(Mailable $mailable): bool
    {
        try {
            // Build the mailable
            $mailable->build();

            // Connect to SMTP server
            if (!$this->connect()) {
                return false;
            }

            // Send the email
            return $this->sendMessage($mailable);
        } catch (\Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Send multiple emails
     */
    public function sendMany(array $mailables): array
    {
        $results = [];

        foreach ($mailables as $mailable) {
            $results[] = $this->send($mailable);
        }

        return $results;
    }

    /**
     * Queue an email for background sending
     */
    public function queue(Mailable $mailable, string $queue = 'default'): bool
    {
        // This would integrate with the queue system
        // For now, just send immediately
        return $this->send($mailable);
    }

    /**
     * Get driver name
     */
    public function getName(): string
    {
        return 'smtp';
    }

    /**
     * Connect to SMTP server
     */
    protected function connect(): bool
    {
        $context = stream_context_create(['ssl' => ['verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,]]);

        $this->connection = stream_socket_client(
            "tcp://{$this->config['host']}:{$this->config['port']}",
            $errno,
            $errstr,
            $this->config['timeout'],
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->connection) {
            return false;
        }

        // Read greeting
        $response = $this->getResponse();
        if (!$this->isPositiveResponse($response)) {
            return false;
        }

        // Send EHLO
        $this->sendCommand("EHLO " . gethostname());

        // Start TLS if required
        if ($this->config['encryption'] === 'tls') {
            $this->sendCommand("STARTTLS");
            stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCommand("EHLO " . gethostname());
        }

        // Authenticate if required
        if ($this->config['auth'] && !empty($this->config['username'])) {
            return $this->authenticate();
        }

        return true;
    }

    /**
     * Authenticate with SMTP server
     */
    protected function authenticate(): bool
    {
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->config['username']));
        $response = $this->sendCommand(base64_encode($this->config['password']));

        return $this->isPositiveResponse($response);
    }

    /**
     * Send the actual email message
     */
    protected function sendMessage(Mailable $mailable): bool
    {
        // Set sender
        $from = $mailable->getFrom() ?: $this->getDefaultFrom();
        $this->sendCommand("MAIL FROM: <" . $this->extractEmail($from) . ">");

        // Set recipient
        $to = $mailable->getTo();
        if (!$to) {
            return false;
        }
        $this->sendCommand("RCPT TO: <" . $this->extractEmail($to) . ">");

        // Add CC recipients
        foreach ($mailable->getCc() as $cc) {
            $this->sendCommand("RCPT TO: <" . $this->extractEmail($cc) . ">");
        }

        // Add BCC recipients
        foreach ($mailable->getBcc() as $bcc) {
            $this->sendCommand("RCPT TO: <" . $this->extractEmail($bcc) . ">");
        }

        // Start data transmission
        $this->sendCommand("DATA");

        // Send headers and body
        $message = $this->buildMessage($mailable);
        $this->sendCommand($message . "\r\n.");

        return true;
    }

    /**
     * Build the complete email message
     */
    protected function buildMessage(Mailable $mailable): string
    {
        $headers = [];

        // Basic headers
        $headers[] = "From: " . ($mailable->getFrom() ?: $this->getDefaultFrom());
        $headers[] = "To: " . $mailable->getTo();
        $headers[] = "Subject: " . $mailable->getSubject();
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . uniqid() . "@" . gethostname() . ">";

        // CC and BCC
        if ($cc = $mailable->getCc()) {
            $headers[] = "Cc: " . implode(', ', $cc);
        }

        if ($replyTo = $mailable->getReplyTo()) {
            $headers[] = "Reply-To: " . $replyTo;
        }

        // Priority
        $priority = $mailable->getPriority();
        if ($priority !== 'normal') {
            $priorityValues = ['high' => '1 (High)',
                'low' => '5 (Low)',];
            if (isset($priorityValues[$priority])) {
                $headers[] = "X-Priority: " . $priorityValues[$priority];
            }
        }

        // Custom headers
        foreach ($mailable->getHeaders() as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        // Content type and body
        $htmlContent = $mailable->getHtmlContent();
        $textContent = $mailable->getTextContent();
        $attachments = $mailable->getAttachments();

        if (!empty($attachments) || ($htmlContent && $textContent)) {
            // Multipart message
            $boundary = uniqid('boundary');
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

            $body = "--{$boundary}\r\n";

            if ($htmlContent && $textContent) {
                // Alternative content
                $altBoundary = uniqid('alt');
                $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";

                $body .= "--{$altBoundary}\r\n";
                $body .= "Content-Type: text/plain; charset = UTF-8\r\n\r\n";
                $body .= $textContent . "\r\n\r\n";

                $body .= "--{$altBoundary}\r\n";
                $body .= "Content-Type: text/html; charset = UTF-8\r\n\r\n";
                $body .= $htmlContent . "\r\n\r\n";

                $body .= "--{$altBoundary}--\r\n";
            } elseif ($htmlContent) {
                $body .= "Content-Type: text/html; charset = UTF-8\r\n\r\n";
                $body .= $htmlContent . "\r\n\r\n";
            } elseif ($textContent) {
                $body .= "Content-Type: text/plain; charset = UTF-8\r\n\r\n";
                $body .= $textContent . "\r\n\r\n";
            }

            // Add attachments
            foreach ($attachments as $attachment) {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: {$attachment['mime_type']}; name=\"{$attachment['name']}\"\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";

                if (isset($attachment['data'])) {
                    $body .= chunk_split(base64_encode($attachment['data']));
                } elseif (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $body .= chunk_split(base64_encode(file_get_contents($attachment['path'])));
                }

                $body .= "\r\n";
            }

            $body .= "--{$boundary}--";
        } else {
            // Simple message
            if ($htmlContent) {
                $headers[] = "Content-Type: text/html; charset = UTF-8";
                $body = $htmlContent;
            } else {
                $headers[] = "Content-Type: text/plain; charset = UTF-8";
                $body = $textContent ?: '';
            }
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * Send command to SMTP server
     */
    protected function sendCommand(string $command): string
    {
        fwrite($this->connection, $command . "\r\n");
        return $this->getResponse();
    }

    /**
     * Get response from SMTP server
     */
    protected function getResponse(): string
    {
        $response = '';
        while (($line = fgets($this->connection, 512)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return trim($response);
    }

    /**
     * Check if response is positive (2xx or 3xx)
     */
    protected function isPositiveResponse(string $response): bool
    {
        return in_array(substr($response, 0, 1), ['2', '3']);
    }

    /**
     * Extract email address from "Name <email>" format
     */
    protected function extractEmail(string $address): string
    {
        if (preg_match('/<(.+)>/', $address, $matches)) {
            return $matches[1];
        }
        return $address;
    }

    /**
     * Get default from address
     */
    protected function getDefaultFrom(): string
    {
        $email = $this->config['from_email'];
        $name = $this->config['from_name'];

        if ($name) {
            return "{$name} <{$email}>";
        }

        return $email;
    }

    /**
     * Disconnect from SMTP server
     */
    protected function disconnect(): void
    {
        if ($this->connection) {
            fwrite($this->connection, "QUIT\r\n");
            fclose($this->connection);
            $this->connection = null;
        }
    }
}
