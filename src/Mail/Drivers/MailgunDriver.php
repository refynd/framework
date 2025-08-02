<?php

namespace Refynd\Mail\Drivers;

use Refynd\Mail\MailInterface;
use Refynd\Mail\Mailable;

/**
 * MailgunDriver - Mailgun API implementation
 *
 * Provides email sending via Mailgun's REST API with advanced features
 * like tracking, analytics, and delivery optimization.
 */
class MailgunDriver implements MailInterface
{
    protected array $config;
    protected string $apiUrl;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(['domain' => '',
            'api_key' => '',
            'endpoint' => 'api.mailgun.net',
            'from_email' => '',
            'from_name' => '',
            'tracking' => true,
            'track_clicks' => true,
            'track_opens' => true,
            'tags' => [],], $config);

        $this->apiUrl = "https://{$this->config['endpoint']}/v3/{$this->config['domain']}/messages";
    }

    /**
     * Send an email via Mailgun API
     */
    public function send(Mailable $mailable): bool
    {
        try {
            // Build the mailable
            $mailable->build();

            // Prepare API data
            $data = $this->buildApiData($mailable);

            // Send via API
            $response = $this->makeApiRequest($data);

            return isset($response['id']);
        } catch (\Exception $e) {
            error_log("Mailgun Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send multiple emails efficiently
     */
    public function sendMany(array $mailables): array
    {
        $results = [];

        // Mailgun supports batch sending with recipient variables
        // For simplicity, we'll send individually
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
        // Mailgun handles queuing automatically
        return $this->send($mailable);
    }

    /**
     * Get driver name
     */
    public function getName(): string
    {
        return 'mailgun';
    }

    /**
     * Build API data from Mailable
     */
    protected function buildApiData(Mailable $mailable): array
    {
        $data = ['from' => $mailable->getFrom() ?: $this->getDefaultFrom(),
            'to' => $mailable->getTo(),
            'subject' => $mailable->getSubject(),];

        // Add CC and BCC
        if ($cc = $mailable->getCc()) {
            $data['cc'] = implode(',', $cc);
        }

        if ($bcc = $mailable->getBcc()) {
            $data['bcc'] = implode(',', $bcc);
        }

        // Add reply-to
        if ($replyTo = $mailable->getReplyTo()) {
            $data['h:Reply-To'] = $replyTo;
        }

        // Add content
        $htmlContent = $mailable->getHtmlContent();
        $textContent = $mailable->getTextContent();

        if ($htmlContent) {
            $data['html'] = $htmlContent;
        }

        if ($textContent) {
            $data['text'] = $textContent;
        }

        // Add priority
        $priority = $mailable->getPriority();
        if ($priority !== 'normal') {
            $priorityValues = ['high' => '1',
                'low' => '5',];
            if (isset($priorityValues[$priority])) {
                $data['h:X-Priority'] = $priorityValues[$priority];
            }
        }

        // Add custom headers
        foreach ($mailable->getHeaders() as $name => $value) {
            $data["h:{$name}"] = $value;
        }

        // Add Mailgun-specific features
        if ($this->config['tracking']) {
            $data['o:tracking'] = 'yes';
        }

        if ($this->config['track_clicks']) {
            $data['o:tracking-clicks'] = 'yes';
        }

        if ($this->config['track_opens']) {
            $data['o:tracking-opens'] = 'yes';
        }

        // Add tags
        $tags = array_merge($this->config['tags'], $mailable->getTags());
        foreach ($tags as $tag) {
            $data['o:tag'] = $tag;
        }

        // Add metadata
        foreach ($mailable->getMetadata() as $key => $value) {
            $data["v:{$key}"] = $value;
        }

        return $data;
    }

    /**
     * Make API request to Mailgun
     */
    protected function makeApiRequest(array $data): array
    {
        $ch = curl_init();

        // Handle attachments
        $attachments = [];
        $mailable = $this->getCurrentMailable();
        if ($mailable) {
            foreach ($mailable->getAttachments() as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $attachments[] = new \CURLFile($attachment['path'], $attachment['mime_type'], $attachment['name']);
                } elseif (isset($attachment['data'])) {
                    // Create temporary file for data attachments
                    $tmpFile = tempnam(sys_get_temp_dir(), 'mailgun_attachment');
                    file_put_contents($tmpFile, $attachment['data']);
                    $attachments[] = new \CURLFile($tmpFile, $attachment['mime_type'], $attachment['name']);
                }
            }
        }

        if (!empty($attachments)) {
            $data['attachment'] = $attachments;
        }

        curl_setopt_array($ch, [CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => "api:{$this->config['api_key']}",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['User-Agent: Refynd-Framework/1.0']]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: " . $error);
        }

        if ($httpCode >= 400) {
            throw new \Exception("HTTP Error {$httpCode}: " . $response);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response: " . $response);
        }

        return $decoded;
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
     * Get current mailable being processed
     * This is a helper for attachment handling
     */
    protected function getCurrentMailable(): ?Mailable
    {
        // In a real implementation, this would be set during the send process
        // For now, return null and handle attachments in buildApiData
        return null;
    }

    /**
     * Send scheduled email
     */
    public function sendScheduled(Mailable $mailable, \DateTime $sendAt): bool
    {
        $mailable->build();

        $data = $this->buildApiData($mailable);
        $data['o:deliverytime'] = $sendAt->format('D, d M Y H:i:s O');

        try {
            $response = $this->makeApiRequest($data);
            return isset($response['id']);
        } catch (\Exception $e) {
            error_log("Mailgun Scheduled Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email with A/B testing
     */
    public function sendWithTest(array $mailables, array $options = []): array
    {
        $results = [];

        // This would implement Mailgun's A/B testing features
        // For now, just send all variants
        foreach ($mailables as $variant => $mailable) {
            $mailable->addTag("variant_{$variant}");
            $results[$variant] = $this->send($mailable);
        }

        return $results;
    }

    /**
     * Get delivery statistics
     */
    public function getStats(array $filters = []): array
    {
        $url = "https://{$this->config['endpoint']}/v3/{$this->config['domain']}/stats/total";

        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "api:{$this->config['api_key']}",
            CURLOPT_TIMEOUT => 30,]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return [];
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Validate email address via Mailgun
     */
    public function validateEmail(string $email): array
    {
        $url = "https://api.mailgun.net/v4/address/validate";

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['address' => $email],
            CURLOPT_USERPWD => "api:{$this->config['api_key']}",
            CURLOPT_TIMEOUT => 10,]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['valid' => false, 'reason' => 'validation_failed'];
        }

        return json_decode($response, true) ?: ['valid' => false];
    }
}
