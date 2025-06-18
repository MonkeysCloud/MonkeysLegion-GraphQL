<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL;

use MonkeysLegion\GraphQL\Schema\SchemaFactory;
use MonkeysLegion\GraphQL\Execution\Executor;
use MonkeysLegion\DI\Container as DIContainer;

/**
 * GraphQL service provider
 *
 * Registers the SchemaFactory and Executor in the DI container.
 * Adjust the bindings according to your container style.
 */
final class GraphQL
{

    /**
     * Register GraphQL services in the DI container.
     *
     * @param DIContainer $c The MonkeysLegion DI container instance
     * @return void
     * @throws \Exception If the container does not support the required services
     */
    public static function register(DIContainer $c): void
    {
        // minimal DI bindings; adjust to your container style
        $c->set(SchemaFactory::class, fn() => new SchemaFactory($c, new Support\Scanner()));
        $c->set(Execution\Executor::class, fn() => new Executor($c->get(SchemaFactory::class)->build()));
    }
}