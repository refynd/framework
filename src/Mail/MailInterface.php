<?php

namespace Refynd\Mail;

/**
 * MailInterface - Contract for mail drivers
 *
 * Defines the interface for sending emails through various providers.
 */
interface MailInterface
{
    /**
     * Send an email
     */
    public function send(Mailable $mailable): bool;

    /**
     * Send multiple emails
     */
    public function sendMany(array $mailables): array;

    /**
     * Queue an email for background sending
     */
    public function queue(Mailable $mailable, string $queue = 'default'): bool;

    /**
     * Get driver name
     */
    public function getName(): string;
}
