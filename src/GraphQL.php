<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL;

use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Cache\SchemaCache;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Executor\QueryExecutor;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use MonkeysLegion\GraphQL\Subscription\PubSubInterface;
use Psr\Container\ContainerInterface;

/**
 * Static facade for the MonkeysLegion GraphQL package.
 *
 * Provides convenient static access to the most commonly used services.
 * Requires a PSR-11 container to be set before use.
 */
final class GraphQL
{
    /** @var ContainerInterface|null */
    private static ?ContainerInterface $container = null;

    /**
     * Set the DI container.
     *
     * @param ContainerInterface $container PSR-11 container
     *
     * @return void
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * Get the GraphQL configuration.
     *
     * @return GraphQLConfig
     */
    public static function config(): GraphQLConfig
    {
        return self::resolve(GraphQLConfig::class);
    }

    /**
     * Build the GraphQL schema.
     *
     * @param array<string>|null $directories Override scan directories
     *
     * @return \GraphQL\Type\Schema
     */
    public static function schema(?array $directories = null): \GraphQL\Type\Schema
    {
        $builder = self::resolve(SchemaBuilder::class);
        $dirs = $directories ?? self::config()->scanDirectories();
        return $builder->build($dirs);
    }

    /**
     * Execute a GraphQL query.
     *
     * @param string                    $query         The query string
     * @param array<string, mixed>|null $variables     Variable values
     * @param string|null               $operationName Operation name
     *
     * @return array{data?: array<string, mixed>|null, errors?: array<array<string, mixed>>}
     */
    public static function execute(
        string $query,
        ?array $variables = null,
        ?string $operationName = null,
    ): array {
        $executor = self::resolve(QueryExecutor::class);
        $schema = self::schema();

        $context = new GraphQLContext(
            request: null,
            user: null,
            container: self::$container,
            loaders: self::resolve(DataLoaderRegistry::class),
        );

        return $executor->execute($schema, $query, $context, $variables, $operationName);
    }

    /**
     * Publish an event to a subscription channel.
     *
     * @param string $channel Channel/topic name
     * @param mixed  $payload Event payload
     *
     * @return void
     */
    public static function publish(string $channel, mixed $payload): void
    {
        $pubSub = self::resolve(PubSubInterface::class);
        $pubSub->publish($channel, $payload);
    }

    /**
     * Resolve a service from the container.
     *
     * @template T
     *
     * @param class-string<T> $id Service identifier
     *
     * @return T
     *
     * @throws \RuntimeException If no container has been set
     */
    private static function resolve(string $id): mixed
    {
        if (self::$container === null) {
            throw new \RuntimeException(
                'GraphQL facade requires a container. Call GraphQL::setContainer() first.',
            );
        }

        return self::$container->get($id);
    }
}