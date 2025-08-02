<?php

declare(strict_types = 1);

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Phixer\Phixer;
use Refynd\Phixer\PhixerEngine;
use Refynd\Phixer\PhixerConfig;
use Refynd\Console\Commands\PhixerCommand;

/**
 * Phixer Module
 *
 * Registers Phixer services with the framework container.
 */
class PhixerModule extends Module
{
    public function register(Container $container): void
    {
        // Register Phixer configuration
        $container->singleton(PhixerConfig::class, function () {
            return new PhixerConfig();
        });

        // Register Phixer engine
        $container->singleton(PhixerEngine::class, function (Container $container) {
            $projectRoot = $container->get('config.app.project_root') ?? dirname(dirname(__DIR__));
            $config = $container->get(PhixerConfig::class);

            return new PhixerEngine($projectRoot, $config);
        });

        // Register Phixer facade
        $container->singleton('phixer', function (Container $container) {
            $projectRoot = $container->get('config.app.project_root') ?? dirname(dirname(__DIR__));
            Phixer::init($projectRoot);

            return Phixer::class;
        });

        // Register Phixer console command
        $container->singleton(PhixerCommand::class, function (Container $container) {
            $projectRoot = $container->get('config.app.project_root') ?? dirname(dirname(__DIR__));

            return new PhixerCommand($projectRoot);
        });

        // Register command with console kernel if available
        if ($container->has('console.kernel')) {
            $consoleKernel = $container->get('console.kernel');
            if (method_exists($consoleKernel, 'registerCommand')) {
                $consoleKernel->registerCommand('phixer', PhixerCommand::class);
            }
        }
    }

    public function boot(): void
    {
        // Boot method - initialization that requires other services
        // Phixer is initialized during registration
    }

    public function provides(): array
    {
        return [PhixerConfig::class,
            PhixerEngine::class,
            PhixerCommand::class,
            'phixer',];
    }
}
