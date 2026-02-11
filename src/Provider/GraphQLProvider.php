<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Provider;

use MonkeysLegion\Core\Provider\ProviderInterface;
use MonkeysLegion\DI\ContainerBuilder;
use MonkeysLegion\GraphQL\Builder\ArgumentBuilder;
use MonkeysLegion\GraphQL\Builder\EnumBuilder;
use MonkeysLegion\GraphQL\Builder\FieldBuilder;
use MonkeysLegion\GraphQL\Builder\InputTypeBuilder;
use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Builder\TypeBuilder;
use MonkeysLegion\GraphQL\Cache\SchemaCache;
use MonkeysLegion\GraphQL\Cache\SchemaCacheWarmer;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Error\ErrorHandler;
use MonkeysLegion\GraphQL\Executor\BatchExecutor;
use MonkeysLegion\GraphQL\Executor\QueryExecutor;
use MonkeysLegion\GraphQL\Http\GraphiQLMiddleware;
use MonkeysLegion\GraphQL\Http\GraphQLMiddleware;
use MonkeysLegion\GraphQL\Http\RequestParser;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use MonkeysLegion\GraphQL\Resolver\ResolverFactory;
use MonkeysLegion\GraphQL\Scanner\AttributeScanner;
use MonkeysLegion\GraphQL\Security\PersistedQueries;
use MonkeysLegion\GraphQL\Security\RateLimiter;
use MonkeysLegion\GraphQL\Subscription\InMemoryPubSub;
use MonkeysLegion\GraphQL\Subscription\PubSubInterface;
use MonkeysLegion\GraphQL\Subscription\SubscriptionManager;
use MonkeysLegion\GraphQL\Subscription\SubscriptionServer;
use MonkeysLegion\GraphQL\Subscription\WsAuthenticator;
use MonkeysLegion\GraphQL\Upload\UploadMiddleware;
use MonkeysLegion\GraphQL\Validation\InputValidator;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Auto-registered service provider for MonkeysLegion-GraphQL.
 *
 * Registers all GraphQL services, middleware, and commands into
 * the MonkeysLegion DI container.
 */
final class GraphQLProvider implements ProviderInterface
{
    /** @var object|null Logger instance (MonkeysLoggerInterface if available) */
    private static ?object $logger = null;

    /**
     * Register all GraphQL services.
     *
     * @param string           $root Application root path
     * @param ContainerBuilder $c    DI container builder
     *
     * @return void
     */
    public static function register(string $root, ContainerBuilder $c): void
    {
        // --- Configuration ---
        $c->set(GraphQLConfig::class, static function () use ($root): GraphQLConfig {
            $configPath = $root . '/config/graphql.mlc';
            $data = [];

            if (file_exists($configPath) && class_exists(\MonkeysLegion\Mlc\Loader::class)) {
                $loader = new \MonkeysLegion\Mlc\Loader(new \MonkeysLegion\Mlc\Parser());
                $config = $loader->load($configPath);
                $data = $config->all();
            }

            return new GraphQLConfig($data);
        });

        // --- Scanner ---
        $c->set(AttributeScanner::class, static fn() => new AttributeScanner());

        // --- Builders ---
        $c->set(ArgumentBuilder::class, static fn(ContainerInterface $di) => new ArgumentBuilder(
            $di,
        ));
        $c->set(EnumBuilder::class, static fn() => new EnumBuilder());
        $c->set(FieldBuilder::class, static fn(ContainerInterface $di) => new FieldBuilder(
            $di->get(ArgumentBuilder::class),
            $di,
        ));
        $c->set(InputTypeBuilder::class, static fn(ContainerInterface $di) => new InputTypeBuilder(
            $di->get(ArgumentBuilder::class),
        ));
        $c->set(TypeBuilder::class, static fn(ContainerInterface $di) => new TypeBuilder(
            $di->get(FieldBuilder::class),
        ));
        $c->set(SchemaBuilder::class, static fn(ContainerInterface $di) => new SchemaBuilder(
            $di->get(AttributeScanner::class),
            $di->get(TypeBuilder::class),
            $di->get(EnumBuilder::class),
            $di->get(InputTypeBuilder::class),
            $di->get(FieldBuilder::class),
            $di->get(ArgumentBuilder::class),
            $di,
        ));

        // --- Error Handling ---
        $c->set(ErrorHandler::class, static fn(ContainerInterface $di) => new ErrorHandler(
            $di->get(GraphQLConfig::class)->debugMode(),
        ));

        // --- Execution ---
        $c->set(QueryExecutor::class, static fn(ContainerInterface $di) => new QueryExecutor(
            $di->get(ErrorHandler::class),
        ));
        $c->set(BatchExecutor::class, static fn(ContainerInterface $di) => new BatchExecutor(
            $di->get(QueryExecutor::class),
        ));

        // --- HTTP ---
        $c->set(RequestParser::class, static fn() => new RequestParser());
        $c->set(GraphQLMiddleware::class, static fn(ContainerInterface $di) => new GraphQLMiddleware(
            $di->get(GraphQLConfig::class),
            $di->get(SchemaBuilder::class),
            $di->get(QueryExecutor::class),
            $di->get(RequestParser::class),
            $di,
        ));
        $c->set(GraphiQLMiddleware::class, static fn(ContainerInterface $di) => new GraphiQLMiddleware(
            $di->get(GraphQLConfig::class),
        ));

        // --- Upload ---
        $c->set(UploadMiddleware::class, static fn() => new UploadMiddleware());

        // --- Resolver ---
        $c->set(ResolverFactory::class, static fn(ContainerInterface $di) => new ResolverFactory($di));

        // --- DataLoader ---
        $c->set(DataLoaderRegistry::class, static fn() => new DataLoaderRegistry());

        // --- Validation ---
        $c->set(InputValidator::class, static fn() => new InputValidator());

        // --- Cache (conditional on PSR-16 availability) ---
        $c->set(SchemaCache::class, static function (ContainerInterface $di): ?SchemaCache {
            if (!$di->has(CacheInterface::class)) {
                return null;
            }
            $config = $di->get(GraphQLConfig::class);
            return new SchemaCache(
                $di->get(CacheInterface::class),
                $config->schemaCacheTtl(),
            );
        });
        $c->set(SchemaCacheWarmer::class, static function (ContainerInterface $di): ?SchemaCacheWarmer {
            $cache = $di->get(SchemaCache::class);
            if ($cache === null) {
                return null;
            }
            return new SchemaCacheWarmer(
                $di->get(SchemaBuilder::class),
                $cache,
                $di->get(GraphQLConfig::class),
            );
        });

        // --- Security (conditional on PSR-16 cache) ---
        $c->set(PersistedQueries::class, static function (ContainerInterface $di): ?PersistedQueries {
            if (!$di->has(CacheInterface::class)) {
                return null;
            }
            return new PersistedQueries($di->get(CacheInterface::class));
        });
        $c->set(RateLimiter::class, static function (ContainerInterface $di): ?RateLimiter {
            if (!$di->has(CacheInterface::class)) {
                return null;
            }
            $config = $di->get(GraphQLConfig::class);
            return new RateLimiter(
                $di->get(CacheInterface::class),
                $config->rateLimitMaxRequests(),
                $config->rateLimitWindowSeconds(),
            );
        });

        // --- Subscriptions ---
        $c->set(PubSubInterface::class, static function (ContainerInterface $di): PubSubInterface {
            $config = $di->get(GraphQLConfig::class);
            $driver = $config->subscriptionDriver();

            if ($driver === 'redis' && extension_loaded('redis')) {
                return new \MonkeysLegion\GraphQL\Subscription\RedisPubSub(
                    $config->redisDsn(),
                );
            }

            return new InMemoryPubSub();
        });
        $c->set(WsAuthenticator::class, static fn() => new WsAuthenticator());
        $c->set(SubscriptionManager::class, static fn(ContainerInterface $di) => new SubscriptionManager(
            $di->get(PubSubInterface::class),
        ));
        $c->set(SubscriptionServer::class, static fn(ContainerInterface $di) => new SubscriptionServer(
            $di->get(SubscriptionManager::class),
            $di->get(WsAuthenticator::class),
        ));

        // --- Route Registration (monkeyslegion-router integration) ---
        $c->set('graphql.routes', static function (ContainerInterface $di): bool {
            if (!$di->has(\MonkeysLegion\Router\Router::class)) {
                return false;
            }

            $router = $di->get(\MonkeysLegion\Router\Router::class);
            $config = $di->get(GraphQLConfig::class);
            $endpoint = $config->endpoint();

            // Register GraphQL endpoint (POST for queries/mutations, GET for simple queries)
            $graphqlHandler = static function (\Psr\Http\Message\ServerRequestInterface $request) use ($di): \Psr\Http\Message\ResponseInterface {
                $middleware = $di->get(GraphQLMiddleware::class);
                $passthrough = new class implements \Psr\Http\Server\RequestHandlerInterface {
                    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
                    {
                        return new \Nyholm\Psr7\Response(404, [], 'Not Found');
                    }
                };
                return $middleware->process($request, $passthrough);
            };

            $router->post($endpoint, $graphqlHandler, 'graphql');
            $router->get($endpoint, $graphqlHandler, 'graphql.get');

            // Register GraphiQL endpoint (GET only, dev environment)
            if ($config->graphiqlEnabled()) {
                $graphiqlEndpoint = $config->graphiqlEndpoint();
                $graphiqlHandler = static function (\Psr\Http\Message\ServerRequestInterface $request) use ($di): \Psr\Http\Message\ResponseInterface {
                    $middleware = $di->get(GraphiQLMiddleware::class);
                    $passthrough = new class implements \Psr\Http\Server\RequestHandlerInterface {
                        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
                        {
                            return new \Nyholm\Psr7\Response(404, [], 'Not Found');
                        }
                    };
                    return $middleware->process($request, $passthrough);
                };
                $router->get($graphiqlEndpoint, $graphiqlHandler, 'graphiql');
            }

            return true;
        });

        // --- GraphQL Facade Bootstrapping ---
        $c->set('graphql.facade', static function (ContainerInterface $di): bool {
            \MonkeysLegion\GraphQL\GraphQL::setContainer($di);
            return true;
        });

        if (self::$logger !== null && method_exists(self::$logger, 'info')) {
            self::$logger->info('[GraphQL] Provider registered');
        }
    }

    /**
     * Set the logger instance.
     *
     * @param object $logger Logger instance (MonkeysLoggerInterface)
     *
     * @return void
     */
    public static function setLogger(object $logger): void
    {
        self::$logger = $logger;
    }
}
