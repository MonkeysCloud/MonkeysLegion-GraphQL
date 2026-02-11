<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Context;

use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Immutable GraphQL execution context.
 *
 * Carries the PSR-7 request, authenticated user, DI container, and
 * DataLoader registry. Passed to every resolver that type-hints it.
 */
final class GraphQLContext
{
    /**
     * @param ServerRequestInterface $request  The incoming HTTP request
     * @param object|null            $user     The authenticated user (null if anonymous)
     * @param ContainerInterface     $container PSR-11 DI container
     * @param DataLoaderRegistry     $loaders  Registry of DataLoader instances
     */
    public function __construct(
        public readonly ServerRequestInterface $request,
        public readonly ?object $user,
        public readonly ContainerInterface $container,
        public readonly DataLoaderRegistry $loaders,
    ) {}

    /**
     * Retrieve a service from the DI container.
     *
     * @template T of object
     *
     * @param class-string<T> $id Service identifier
     *
     * @return T
     */
    public function get(string $id): object
    {
        /** @var T */
        return $this->container->get($id);
    }

    /**
     * Get a named DataLoader from the registry.
     *
     * @param string $name Loader name / key
     *
     * @return \MonkeysLegion\GraphQL\Loader\DataLoader
     */
    public function loader(string $name): \MonkeysLegion\GraphQL\Loader\DataLoader
    {
        return $this->loaders->get($name);
    }
}
