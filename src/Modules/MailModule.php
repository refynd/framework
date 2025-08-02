<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Mail\MailManager;
use Refynd\Mail\MailInterface;

/**
 * MailModule - Integrates mail system with Refynd framework
 *
 * Registers mail services and provides configuration management
 * for email drivers and templates.
 */
class MailModule extends Module
{
    protected Container $container;
    protected array $config = [];

    protected array $defaultConfig = ['default' => 'smtp',
        'drivers' => ['smtp' => ['driver' => 'smtp',
                'host' => 'localhost',
                'port' => 587,
                'username' => '',
                'password' => '',
                'encryption' => 'tls',
                'auth' => true,
                'timeout' => 30,
                'from_email' => '',
                'from_name' => '',],
            'mailgun' => ['driver' => 'mailgun',
                'domain' => '',
                'api_key' => '',
                'endpoint' => 'api.mailgun.net',
                'from_email' => '',
                'from_name' => '',
                'tracking' => true,
                'track_clicks' => true,
                'track_opens' => true,
                'tags' => [],],
            'ses' => ['driver' => 'ses',
                'region' => 'us-east-1',
                'access_key' => '',
                'secret_key' => '',
                'from_email' => '',
                'from_name' => '',
                'configuration_set' => '',],],
        'fallback_drivers' => [],
        'templates' => ['path' => 'resources/mail/templates',
            'extension' => '.php',],];

    /**
     * Get module name
     */
    public function getName(): string
    {
        return 'mail';
    }

    /**
     * Get module version
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return ['container'];
    }

    /**
     * Boot the module
     */
    public function boot(): void
    {
        // Register mail configuration
        $this->container->singleton('mail.config', function () {
            return $this->config;
        });

        // Register mail manager
        $this->container->singleton('mail.manager', function (Container $container) {
            return new MailManager($container, $container->get('mail.config'));
        });

        // Register mail interface
        $this->container->bind(MailInterface::class, function (Container $container) {
            return $container->get('mail.manager')->driver();
        });
    }

    /**
     * Register module services
     */
    public function register(Container $container): void
    {
        $this->container = $container;

        // Register configuration
        $this->registerConfig($container);

        // Register mail services
        $this->registerMailServices($container);

        // Register mail facade
        $this->registerFacade($container);
    }

    /**
     * Register mail configuration
     */
    protected function registerConfig(Container $container): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // Load environment variables
        $config = $this->loadEnvironmentConfig($config);

        $this->config = $config;
        $container->instance('mail.config', $config);
    }

    /**
     * Register mail services
     */
    protected function registerMailServices(Container $container): void
    {
        // Mail manager
        $container->singleton(MailManager::class, function (Container $container) {
            return new MailManager($container, $container->get('mail.config'));
        });

        // Default mail driver
        $container->bind(MailInterface::class, function (Container $container) {
            return $container->get(MailManager::class)->driver();
        });
    }

    /**
     * Register mail facade
     */
    protected function registerFacade(Container $container): void
    {
        if (class_exists('Refynd\Support\Facades\Facade')) {
            // Register Mail facade if facades are available
            $container->singleton('Refynd\Support\Facades\Mail', function (Container $container) {
                return $container->get(MailManager::class);
            });
        }
    }

    /**
     * Load configuration from environment variables
     */
    protected function loadEnvironmentConfig(array $config): array
    {
        // SMTP configuration
        if (isset($_ENV['MAIL_SMTP_HOST'])) {
            $config['drivers']['smtp']['host'] = $_ENV['MAIL_SMTP_HOST'];
        }

        if (isset($_ENV['MAIL_SMTP_PORT'])) {
            $config['drivers']['smtp']['port'] = (int) $_ENV['MAIL_SMTP_PORT'];
        }

        if (isset($_ENV['MAIL_SMTP_USERNAME'])) {
            $config['drivers']['smtp']['username'] = $_ENV['MAIL_SMTP_USERNAME'];
        }

        if (isset($_ENV['MAIL_SMTP_PASSWORD'])) {
            $config['drivers']['smtp']['password'] = $_ENV['MAIL_SMTP_PASSWORD'];
        }

        if (isset($_ENV['MAIL_SMTP_ENCRYPTION'])) {
            $config['drivers']['smtp']['encryption'] = $_ENV['MAIL_SMTP_ENCRYPTION'];
        }

        if (isset($_ENV['MAIL_FROM_EMAIL'])) {
            $config['drivers']['smtp']['from_email'] = $_ENV['MAIL_FROM_EMAIL'];
            $config['drivers']['mailgun']['from_email'] = $_ENV['MAIL_FROM_EMAIL'];
        }

        if (isset($_ENV['MAIL_FROM_NAME'])) {
            $config['drivers']['smtp']['from_name'] = $_ENV['MAIL_FROM_NAME'];
            $config['drivers']['mailgun']['from_name'] = $_ENV['MAIL_FROM_NAME'];
        }

        // Mailgun configuration
        if (isset($_ENV['MAILGUN_DOMAIN'])) {
            $config['drivers']['mailgun']['domain'] = $_ENV['MAILGUN_DOMAIN'];
        }

        if (isset($_ENV['MAILGUN_API_KEY'])) {
            $config['drivers']['mailgun']['api_key'] = $_ENV['MAILGUN_API_KEY'];
        }

        if (isset($_ENV['MAILGUN_ENDPOINT'])) {
            $config['drivers']['mailgun']['endpoint'] = $_ENV['MAILGUN_ENDPOINT'];
        }

        // AWS SES configuration
        if (isset($_ENV['AWS_SES_REGION'])) {
            $config['drivers']['ses']['region'] = $_ENV['AWS_SES_REGION'];
        }

        if (isset($_ENV['AWS_ACCESS_KEY_ID'])) {
            $config['drivers']['ses']['access_key'] = $_ENV['AWS_ACCESS_KEY_ID'];
        }

        if (isset($_ENV['AWS_SECRET_ACCESS_KEY'])) {
            $config['drivers']['ses']['secret_key'] = $_ENV['AWS_SECRET_ACCESS_KEY'];
        }

        if (isset($_ENV['AWS_SES_CONFIGURATION_SET'])) {
            $config['drivers']['ses']['configuration_set'] = $_ENV['AWS_SES_CONFIGURATION_SET'];
        }

        if (isset($_ENV['MAIL_FROM_EMAIL'])) {
            $config['drivers']['ses']['from_email'] = $_ENV['MAIL_FROM_EMAIL'];
        }

        if (isset($_ENV['MAIL_FROM_NAME'])) {
            $config['drivers']['ses']['from_name'] = $_ENV['MAIL_FROM_NAME'];
        }

        // Default driver
        if (isset($_ENV['MAIL_DRIVER'])) {
            $config['default'] = $_ENV['MAIL_DRIVER'];
        }

        return $config;
    }

    /**
     * Get module configuration schema
     */
    public function getConfigSchema(): array
    {
        return ['default' => ['type' => 'string',
                'description' => 'Default mail driver to use',
                'default' => 'smtp',
                'enum' => ['smtp', 'mailgun'],],
            'drivers' => ['type' => 'object',
                'description' => 'Mail driver configurations',
                'properties' => ['smtp' => ['type' => 'object',
                        'properties' => ['host' => ['type' => 'string', 'description' => 'SMTP server host'],
                            'port' => ['type' => 'integer', 'description' => 'SMTP server port'],
                            'username' => ['type' => 'string', 'description' => 'SMTP username'],
                            'password' => ['type' => 'string', 'description' => 'SMTP password'],
                            'encryption' => ['type' => 'string', 'enum' => ['tls', 'ssl', 'none']],
                            'from_email' => ['type' => 'string', 'description' => 'Default from email'],
                            'from_name' => ['type' => 'string', 'description' => 'Default from name'],],],
                    'mailgun' => ['type' => 'object',
                        'properties' => ['domain' => ['type' => 'string', 'description' => 'Mailgun domain'],
                            'api_key' => ['type' => 'string', 'description' => 'Mailgun API key'],
                            'endpoint' => ['type' => 'string', 'description' => 'Mailgun API endpoint'],
                            'from_email' => ['type' => 'string', 'description' => 'Default from email'],
                            'from_name' => ['type' => 'string', 'description' => 'Default from name'],
                            'tracking' => ['type' => 'boolean', 'description' => 'Enable tracking'],],],],],
            'fallback_drivers' => ['type' => 'array',
                'description' => 'Fallback drivers if primary fails',
                'items' => ['type' => 'string'],],
            'templates' => ['type' => 'object',
                'properties' => ['path' => ['type' => 'string', 'description' => 'Template directory path'],
                    'extension' => ['type' => 'string', 'description' => 'Template file extension'],],],];
    }

    /**
     * Validate module configuration
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        // Validate default driver exists
        if (!isset($config['drivers'][$config['default']])) {
            $errors[] = "Default mail driver '{$config['default']}' is not configured";
        }

        // Validate SMTP configuration if present
        if (isset($config['drivers']['smtp'])) {
            $smtp = $config['drivers']['smtp'];

            if (empty($smtp['host'])) {
                $errors[] = 'SMTP host is required';
            }

            if (!is_int($smtp['port']) || $smtp['port'] < 1 || $smtp['port'] > 65535) {
                $errors[] = 'SMTP port must be a valid port number';
            }
        }

        // Validate Mailgun configuration if present
        if (isset($config['drivers']['mailgun'])) {
            $mailgun = $config['drivers']['mailgun'];

            if (empty($mailgun['domain'])) {
                $errors[] = 'Mailgun domain is required';
            }

            if (empty($mailgun['api_key'])) {
                $errors[] = 'Mailgun API key is required';
            }
        }

        return $errors;
    }

    /**
     * Get health check information
     */
    public function getHealthCheck(Container $container): array
    {
        $health = ['status' => 'healthy',
            'drivers' => [],
            'default_driver' => null,];

        try {
            $manager = $container->get(MailManager::class);
            $health['default_driver'] = $manager->getDefaultDriver();

            foreach ($manager->getAvailableDrivers() as $driverName) {
                $health['drivers'][$driverName] = ['available' => $manager->testDriver($driverName),
                    'type' => $driverName,];
            }
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['error'] = $e->getMessage();
        }

        return $health;
    }
}
