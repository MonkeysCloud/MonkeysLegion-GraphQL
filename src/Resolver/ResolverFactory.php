<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Resolver;

use Psr\Container\ContainerInterface;

/**
 * DI-aware factory for creating resolver instances.
 *
 * Resolves Query, Mutation, Subscription, and Type resolver classes from
 * the DI container, ensuring proper dependency injection.
 */
final class ResolverFactory
{
    /**
     * @param ContainerInterface $container PSR-11 DI container
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Create (or retrieve) a resolver instance by class name.
     *
     * @template T of object
     *
     * @param class-string<T> $className The resolver class to instantiate
     *
     * @return T
     */
    public function create(string $className): object
    {
        return $this->container->get($className);
    }

    /**
     * Check if the container can provide a resolver.
     *
     * @param class-string $className The class to check
     *
     * @return bool
     */
    public function has(string $className): bool
    {
        return $this->container->has($className);
    }

    /**
     * Create a resolver and invoke it with given arguments.
     *
     * @param class-string         $className The resolver class
     * @param array<string, mixed> $args      Arguments to pass to __invoke
     *
     * @return mixed
     */
    public function invoke(string $className, array $args = []): mixed
    {
        $instance = $this->create($className);

        if (!method_exists($instance, '__invoke')) {
            throw new \RuntimeException(
                sprintf('Resolver class %s does not have an __invoke method.', $className),
            );
        }

        return $instance(...$args);
    }
}
