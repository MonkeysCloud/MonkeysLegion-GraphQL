<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Cache;

use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;

/**
 * Warms the schema cache by building the schema and storing it.
 *
 * Intended for use by CLI commands or deployment scripts.
 */
final class SchemaCacheWarmer
{
    /**
     * @param SchemaBuilder  $schemaBuilder The schema builder
     * @param SchemaCache    $schemaCache   The schema cache
     * @param GraphQLConfig  $config        Configuration
     */
    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
        private readonly SchemaCache $schemaCache,
        private readonly GraphQLConfig $config,
    ) {}

    /**
     * Warm the schema cache.
     *
     * Builds the schema from configured directories and stores it in the cache.
     *
     * @param string $cacheKey Optional custom cache key (defaults to 'default')
     *
     * @return void
     */
    public function warm(string $cacheKey = 'default'): void
    {
        $schema = $this->schemaBuilder->build($this->config->scanDirectories());
        $this->schemaCache->put($cacheKey, $schema);
    }

    /**
     * Clear and re-warm the schema cache.
     *
     * @param string $cacheKey Optional custom cache key (defaults to 'default')
     *
     * @return void
     */
    public function refresh(string $cacheKey = 'default'): void
    {
        $this->schemaCache->clear($cacheKey);
        $this->warm($cacheKey);
    }
}
