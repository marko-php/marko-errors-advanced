<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsSimple\Environment;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        // Closure: the constructor's optional FormatterInterface cannot be
        // autowired, and the handler must be built with the real Environment
        // so production renders the safe generic page.
        ErrorHandlerInterface::class => function (ContainerInterface $container): ErrorHandlerInterface {
            return new AdvancedErrorHandler(
                environment: $container->get(Environment::class),
            );
        },
    ],
    'boot' => function (ContainerInterface $container) {
        // Get the error handler and register it
        $handler = $container->get(ErrorHandlerInterface::class);
        $handler->register();
    },
];
