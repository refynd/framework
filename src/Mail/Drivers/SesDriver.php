<?php

namespace Refynd\Mail\Drivers;

use Refynd\Mail\MailInterface;
use Refynd\Mail\Mailable;

/**
 * SesDriver - Amazon Simple Email Service implementation
 *
 * Provides email sending via AWS SES with authentication
 * and delivery features.
 */
class SesDriver implements MailInterface
{
    protected array $config;
    protected string $apiUrl;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(['region' => 'us-east-1',
            'access_key' => '',
            'secret_key' => '',
            'from_email' => '',
            'from_name' => '',
            'configuration_set' => '',], $config);

        $this->apiUrl = "https://email.{$this->config['region']}.amazonaws.com/";
    }

    /**
     * Send an email via AWS SES
     */
    public function send(Mailable $mailable): bool
    {
        try {
            // Build the mailable
            $mailable->build();

            // Prepare SES request
            $params = $this->buildSesParams($mailable);

            // Send via SES API
            $response = $this->makeApiRequest('SendEmail', $params);

            return isset($response['MessageId']);
        } catch (\Exception $e) {
            error_log("SES Error: " . $e->getMessage());
            return false;
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
        // SES doesn't have built-in queuing, send immediately
        return $this->send($mailable);
    }

    /**
     * Get driver name
     */
    public function getName(): string
    {
        return 'ses';
    }

    /**
     * Build SES API parameters
     */
    protected function buildSesParams(Mailable $mailable): array
    {
        $params = ['Source' => $mailable->getFrom() ?: $this->getDefaultFrom(),
            'Destination' => ['ToAddresses' => [$this->extractEmail($mailable->getTo())],],
            'Message' => ['Subject' => ['Data' => $mailable->getSubject(),
                    'Charset' => 'UTF-8',],
                'Body' => [],],];

        // Add CC addresses
        if ($cc = $mailable->getCc()) {
            $params['Destination']['CcAddresses'] = array_map([$this, 'extractEmail'], $cc);
        }

        // Add BCC addresses
        if ($bcc = $mailable->getBcc()) {
            $params['Destination']['BccAddresses'] = array_map([$this, 'extractEmail'], $bcc);
        }

        // Add reply-to
        if ($replyTo = $mailable->getReplyTo()) {
            $params['ReplyToAddresses'] = [$replyTo];
        }

        // Add content
        $htmlContent = $mailable->getHtmlContent();
        $textContent = $mailable->getTextContent();

        if ($htmlContent) {
            $params['Message']['Body']['Html'] = ['Data' => $htmlContent,
                'Charset' => 'UTF-8',];
        }

        if ($textContent) {
            $params['Message']['Body']['Text'] = ['Data' => $textContent,
                'Charset' => 'UTF-8',];
        }

        // Add configuration set if specified
        if (!empty($this->config['configuration_set'])) {
            $params['ConfigurationSetName'] = $this->config['configuration_set'];
        }

        // Add tags
        $tags = $mailable->getTags();
        if (!empty($tags)) {
            $params['Tags'] = [];
            foreach ($tags as $tag) {
                $params['Tags'][] = ['Name' => 'tag',
                    'Value' => $tag,];
            }
        }

        return $params;
    }

    /**
     * Make API request to AWS SES
     */
    protected function makeApiRequest(string $action, array $params): array
    {
        $params['Action'] = $action;
        $params['Version'] = '2010-12-01';

        $queryString = $this->buildQueryString($params);
        $headers = $this->buildHeaders($queryString, $action);

        $ch = curl_init();

        curl_setopt_array($ch, [CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $queryString,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,]);

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

        // Parse XML response
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new \Exception("Invalid XML response: " . $response);
        }

        return $this->xmlToArray($xml);
    }

    /**
     * Build query string from parameters
     */
    protected function buildQueryString(array $params): string
    {
        $queryParts = [];
        $this->buildQueryParts($params, '', $queryParts);
        ksort($queryParts);

        return implode('&', $queryParts);
    }

    /**
     * Recursively build query parts
     */
    protected function buildQueryParts(array $params, string $prefix, array &$queryParts): void
    {
        foreach ($params as $key => $value) {
            $paramKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // Indexed array
                    foreach ($value as $index => $item) {
                        $indexKey = $paramKey . '.' . ($index + 1);
                        if (is_array($item)) {
                            $this->buildQueryParts($item, $indexKey, $queryParts);
                        } else {
                            $queryParts[] = urlencode($indexKey) . '=' . urlencode($item);
                        }
                    }
                } else {
                    // Associative array
                    $this->buildQueryParts($value, $paramKey, $queryParts);
                }
            } else {
                $queryParts[] = urlencode($paramKey) . '=' . urlencode($value);
            }
        }
    }

    /**
     * Build HTTP headers with AWS Signature Version 4
     */
    protected function buildHeaders(string $queryString, string $action): array
    {
        $host = parse_url($this->apiUrl, PHP_URL_HOST);
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $headers = ['Host: ' . $host,
            'Content-Type: application/x-www-form-urlencoded',
            'X-Amz-Date: ' . $timestamp,];

        // Create signature
        $signature = $this->createSignature($queryString, $timestamp, $date, $host);
        $headers[] = 'Authorization: ' . $signature;

        return $headers;
    }

    /**
     * Create AWS Signature Version 4
     */
    protected function createSignature(string $payload, string $timestamp, string $date, string $host): string
    {
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$date}/{$this->config['region']}/ses/aws4_request";

        // Canonical request
        $canonicalRequest = "POST\n/\n\nhost:{$host}\nx-amz-date:{$timestamp}\n\nhost;x-amz-date\n" . hash('sha256', $payload);

        // String to sign
        $stringToSign = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Signing key
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->config['secret_key'], true);
        $kRegion = hash_hmac('sha256', $this->config['region'], $kDate, true);
        $kService = hash_hmac('sha256', 'ses', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        // Signature
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Authorization header
        $credential = "{$this->config['access_key']}/{$credentialScope}";
        return "{$algorithm} Credential={$credential}, SignedHeaders = host;x-amz-date, Signature={$signature}";
    }

    /**
     * Convert XML to array
     */
    protected function xmlToArray(\SimpleXMLElement $xml): array
    {
        return json_decode(json_encode($xml), true);
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
     * Send raw email (for advanced use cases)
     */
    public function sendRaw(string $rawMessage, array $destinations = []): bool
    {
        try {
            $params = ['RawMessage' => ['Data' => base64_encode($rawMessage),],];

            if (!empty($destinations)) {
                $params['Destinations'] = $destinations;
            }

            $response = $this->makeApiRequest('SendRawEmail', $params);
            return isset($response['MessageId']);
        } catch (\Exception $e) {
            error_log("SES Raw Email Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get sending quota
     */
    public function getSendingQuota(): array
    {
        try {
            $response = $this->makeApiRequest('GetSendQuota', []);
            return ['max_24_hour' => $response['Max24HourSend'] ?? 0,
                'max_send_rate' => $response['MaxSendRate'] ?? 0,
                'sent_last_24_hours' => $response['SentLast24Hours'] ?? 0,];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get sending statistics
     */
    public function getSendingStats(): array
    {
        try {
            $response = $this->makeApiRequest('GetSendStatistics', []);
            return $response['SendDataPoints'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
