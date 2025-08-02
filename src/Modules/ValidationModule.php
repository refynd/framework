<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Validation\Validator;

class ValidationModule extends Module
{
    public function register(Container $container): void
    {
        $container->bind(Validator::class, function ($container, $parameters) {
            $data = $parameters['data'] ?? [];
            $rules = $parameters['rules'] ?? [];
            $messages = $parameters['messages'] ?? [];

            return new Validator($data, $rules, $messages);
        });
    }

    public function boot(): void
    {
        // Validation module is ready for use
    }

    public function getDependencies(): array
    {
        return [];
    }
}
