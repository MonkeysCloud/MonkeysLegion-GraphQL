<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Config;

/**
 * Typed configuration for the GraphQL package.
 *
 * Reads settings from config/graphql.mlc via the MonkeysLegion MLC reader.
 * All values are lazily loaded and provide sensible defaults.
 *
 * @phpstan-type SecurityConfig array{max_depth: int, max_complexity: int, introspection: bool}
 */
final class GraphQLConfig
{
    /** @var array<string, mixed> */
    private readonly array $data;

    /**
     * @param array<string, mixed> $data Raw config key-value pairs from .mlc reader
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /* ------------------------------------------------------------------
     * HTTP
     * ----------------------------------------------------------------*/

    /**
     * The HTTP endpoint path for GraphQL requests.
     *
     * @return string
     */
    public function endpoint(): string
    {
        return (string) ($this->data['graphql.endpoint'] ?? '/graphql');
    }

    /**
     * The HTTP endpoint path for the GraphiQL IDE.
     *
     * @return string
     */
    public function graphiqlEndpoint(): string
    {
        return (string) ($this->data['graphql.graphiql_endpoint'] ?? '/graphiql');
    }

    /**
     * Whether the GraphiQL IDE is enabled.
     *
     * @return bool
     */
    public function graphiqlEnabled(): bool
    {
        return (bool) ($this->data['graphql.graphiql_enabled'] ?? true);
    }

    /* ------------------------------------------------------------------
     * Schema Scanning
     * ----------------------------------------------------------------*/

    /**
     * Directories to scan for GraphQL type/query/mutation classes.
     *
     * @return array<string>
     */
    public function scanDirectories(): array
    {
        $dirs = $this->data['graphql.scan_dirs'] ?? ['app/GraphQL'];
        return is_array($dirs) ? $dirs : [(string) $dirs];
    }

    /**
     * The namespace prefix for scanned classes.
     *
     * @return string
     */
    public function scanNamespace(): string
    {
        return (string) ($this->data['graphql.scan_namespace'] ?? 'App\\GraphQL');
    }

    /* ------------------------------------------------------------------
     * Security
     * ----------------------------------------------------------------*/

    /**
     * Maximum query depth allowed (0 = unlimited).
     *
     * @return int
     */
    public function maxDepth(): int
    {
        return (int) ($this->data['graphql.security.max_depth'] ?? 10);
    }

    /**
     * Maximum query complexity allowed (0 = unlimited).
     *
     * @return int
     */
    public function maxComplexity(): int
    {
        return (int) ($this->data['graphql.security.max_complexity'] ?? 1000);
    }

    /**
     * Whether GraphQL introspection is enabled.
     *
     * @return bool
     */
    public function introspectionEnabled(): bool
    {
        return (bool) ($this->data['graphql.security.introspection'] ?? true);
    }

    /**
     * Whether persisted queries (APQ) are enabled.
     *
     * @return bool
     */
    public function persistedQueriesEnabled(): bool
    {
        return (bool) ($this->data['graphql.security.persisted_queries'] ?? false);
    }

    /**
     * Rate limit: max requests per window per client.
     *
     * @return int
     */
    public function rateLimitMaxRequests(): int
    {
        return (int) ($this->data['graphql.security.rate_limit.max_requests'] ?? 100);
    }

    /**
     * Rate limit: time window in seconds.
     *
     * @return int
     */
    public function rateLimitWindowSeconds(): int
    {
        return (int) ($this->data['graphql.security.rate_limit.window_seconds'] ?? 60);
    }

    /* ------------------------------------------------------------------
     * Caching
     * ----------------------------------------------------------------*/

    /**
     * Whether schema caching is enabled.
     *
     * @return bool
     */
    public function schemaCacheEnabled(): bool
    {
        return (bool) ($this->data['graphql.cache.enabled'] ?? false);
    }

    /**
     * Time-to-live for cached schema in seconds (0 = forever).
     *
     * @return int
     */
    public function schemaCacheTtl(): int
    {
        return (int) ($this->data['graphql.cache.ttl'] ?? 3600);
    }

    /* ------------------------------------------------------------------
     * Subscriptions
     * ----------------------------------------------------------------*/

    /**
     * Whether subscriptions are enabled.
     *
     * @return bool
     */
    public function subscriptionsEnabled(): bool
    {
        return (bool) ($this->data['graphql.subscriptions.enabled'] ?? false);
    }

    /**
     * The PubSub driver to use: 'memory' or 'redis'.
     *
     * @return string
     */
    public function subscriptionDriver(): string
    {
        return (string) ($this->data['graphql.subscriptions.driver'] ?? 'memory');
    }

    /**
     * WebSocket server host.
     *
     * @return string
     */
    public function subscriptionHost(): string
    {
        return (string) ($this->data['graphql.subscriptions.host'] ?? '0.0.0.0');
    }

    /**
     * WebSocket server port.
     *
     * @return int
     */
    public function subscriptionPort(): int
    {
        return (int) ($this->data['graphql.subscriptions.port'] ?? 6001);
    }

    /**
     * Redis DSN for Redis-backed PubSub.
     *
     * @return string
     */
    public function redisDsn(): string
    {
        return (string) ($this->data['graphql.subscriptions.redis_dsn'] ?? 'redis://127.0.0.1:6379');
    }

    /* ------------------------------------------------------------------
     * Debug / Environment
     * ----------------------------------------------------------------*/

    /**
     * Whether debug mode is enabled (affects error output verbosity).
     *
     * @return bool
     */
    public function debugMode(): bool
    {
        return (bool) ($this->data['graphql.debug'] ?? false);
    }

    /**
     * Get a raw config value by dot-notation key.
     *
     * @param string $key     Dot-notation config key
     * @param mixed  $default Default value if key is missing
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
