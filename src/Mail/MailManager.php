<?php

namespace Refynd\Mail;

use Refynd\Container\Container;
use Refynd\Mail\Drivers\SmtpDriver;
use Refynd\Mail\Drivers\MailgunDriver;
use Refynd\Mail\Drivers\SesDriver;

/**
 * MailManager - Manages email drivers and configuration
 *
 * Provides a unified interface for sending emails across multiple
 * drivers with fallback support and driver selection.
 */
class MailManager
{
    protected Container $container;
    protected array $config;
    protected array $drivers = [];
    protected string $defaultDriver;

    public function __construct(Container $container, array $config = [])
    {
        $this->container = $container;
        $this->config = array_merge(['default' => 'smtp',
            'drivers' => ['smtp' => ['driver' => 'smtp',
                    'host' => 'localhost',
                    'port' => 587,
                    'username' => '',
                    'password' => '',
                    'encryption' => 'tls',
                    'from_email' => '',
                    'from_name' => '',],
                'mailgun' => ['driver' => 'mailgun',
                    'domain' => '',
                    'api_key' => '',
                    'from_email' => '',
                    'from_name' => '',],],
            'fallback_drivers' => [],], $config);

        $this->defaultDriver = $this->config['default'];
    }

    /**
     * Send an email using the default driver
     */
    public function send(Mailable $mailable): bool
    {
        return $this->driver()->send($mailable);
    }

    /**
     * Send an email using a specific driver
     */
    public function sendVia(string $driverName, Mailable $mailable): bool
    {
        return $this->driver($driverName)->send($mailable);
    }

    /**
     * Send an email with fallback support
     */
    public function sendWithFallback(Mailable $mailable): bool
    {
        $drivers = [$this->defaultDriver];
        $drivers = array_merge($drivers, $this->config['fallback_drivers']);

        foreach ($drivers as $driverName) {
            try {
                if ($this->driver($driverName)->send($mailable)) {
                    return true;
                }
            } catch (\Exception $e) {
                error_log("Mail driver '{$driverName}' failed: " . $e->getMessage());
                continue;
            }
        }

        return false;
    }

    /**
     * Send multiple emails
     */
    public function sendMany(array $mailables): array
    {
        return $this->driver()->sendMany($mailables);
    }

    /**
     * Queue an email for background sending
     */
    public function queue(Mailable $mailable, string $queue = 'default'): bool
    {
        return $this->driver()->queue($mailable, $queue);
    }

    /**
     * Get a driver instance
     */
    public function driver(?string $name = null): MailInterface
    {
        $name = $name ?: $this->defaultDriver;

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Create a driver instance
     */
    protected function createDriver(string $name): MailInterface
    {
        if (!isset($this->config['drivers'][$name])) {
            throw new \InvalidArgumentException("Mail driver '{$name}' is not configured.");
        }

        $config = $this->config['drivers'][$name];
        $driverType = $config['driver'] ?? $name;

        return match ($driverType) {
            'smtp' => new SmtpDriver($config),
            'mailgun' => new MailgunDriver($config),
            'ses' => new SesDriver($config),
            default => throw new \InvalidArgumentException("Unsupported mail driver: {$driverType}")
        };
    }

    /**
     * Set the default driver
     */
    public function setDefaultDriver(string $name): self
    {
        $this->defaultDriver = $name;
        return $this;
    }

    /**
     * Get the default driver name
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Get all configured drivers
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->config['drivers']);
    }

    /**
     * Test a driver connection
     */
    public function testDriver(string $name): bool
    {
        try {
            $driver = $this->driver($name);

            // Create a simple test email
            $testMail = new class () extends Mailable {
                public function build(): static
                {
                    return $this->subject('Test Email')->html('<p > Test</p>');
                }
            };

            // For testing, we'll just check if the driver can be created
            // In a real scenario, you might want to send to a test address
            return $driver instanceof MailInterface;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get driver statistics (if supported)
     */
    public function getStats(string $driverName, array $filters = []): array
    {
        $driver = $this->driver($driverName);

        // Only Mailgun driver currently supports stats
        if ($driver instanceof MailgunDriver) {
            return $driver->getStats($filters);
        }

        return [];
    }

    /**
     * Validate an email address (if driver supports it)
     */
    public function validateEmail(string $email, ?string $driverName = null): array
    {
        $driver = $this->driver($driverName);

        // Only Mailgun driver currently supports validation
        if ($driver instanceof MailgunDriver) {
            return $driver->validateEmail($email);
        }

        // Basic validation fallback
        return ['valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
            'reason' => 'basic_validation'];
    }

    /**
     * Create a mailable instance with pre-configured defaults
     */
    public function mailable(): MailableBuilder
    {
        return new MailableBuilder($this);
    }

    /**
     * Send a quick email without creating a mailable class
     */
    public function quick(string $to, string $subject, string $content, array $options = []): bool
    {
        $mailable = new class ($to, $subject, $content, $options) extends Mailable {
            public function __construct(
                private string $recipient,
                private string $emailSubject,
                private string $emailContent,
                private array $emailOptions
            ) {
            }

            public function build(): static
            {
                $this->to($this->recipient)
                     ->subject($this->emailSubject);

                // Determine if content is HTML
                if (isset($this->emailOptions['html']) && $this->emailOptions['html']) {
                    $this->html($this->emailContent);
                } else {
                    $this->text($this->emailContent);
                }

                // Apply other options
                if (isset($this->emailOptions['from'])) {
                    $this->from($this->emailOptions['from']);
                }

                if (isset($this->emailOptions['priority'])) {
                    $this->priority($this->emailOptions['priority']);
                }

                return $this;
            }
        };

        return $this->send($mailable);
    }

    /**
     * Send a templated email quickly
     */
    public function template(string $to, string $subject, string $template, array $data = []): bool
    {
        $mailable = new class ($to, $subject, $template, $data) extends Mailable {
            public function __construct(
                private string $recipient,
                private string $emailSubject,
                private string $templateName,
                private array $templateData
            ) {
            }

            public function build(): static
            {
                return $this->to($this->recipient)
                           ->subject($this->emailSubject)
                           ->template($this->templateName, $this->templateData);
            }
        };

        return $this->send($mailable);
    }
}

/**
 * MailableBuilder - Fluent builder for quick mailable creation
 */
class MailableBuilder
{
    protected MailManager $manager;
    protected string $to = '';
    protected string $subject = '';
    protected string $content = '';
    protected array $options = [];

    public function __construct(MailManager $manager)
    {
        $this->manager = $manager;
    }

    public function to(string $email): self
    {
        $this->to = $email;
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $content): self
    {
        $this->content = $content;
        $this->options['html'] = true;
        return $this;
    }

    public function text(string $content): self
    {
        $this->content = $content;
        $this->options['html'] = false;
        return $this;
    }

    public function from(string $email): self
    {
        $this->options['from'] = $email;
        return $this;
    }

    public function priority(string $priority): self
    {
        $this->options['priority'] = $priority;
        return $this;
    }

    public function send(): bool
    {
        return $this->manager->quick($this->to, $this->subject, $this->content, $this->options);
    }
}
