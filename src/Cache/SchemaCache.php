<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Cache;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 schema caching.
 *
 * Caches the serialized schema definition to avoid re-scanning and
 * re-building on every request.
 */
final class SchemaCache
{
    /** @var string Cache key prefix */
    private const CACHE_PREFIX = 'ml_graphql_schema_';

    /**
     * @param CacheInterface $cache PSR-16 cache implementation
     * @param int            $ttl   Time-to-live in seconds (0 = forever)
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
    ) {}

    /**
     * Get a cached schema or build and cache it.
     *
     * @param string   $key     Cache key identifier
     * @param callable $builder Callback that builds the Schema if not cached
     *
     * @return Schema
     */
    public function getOrBuild(string $key, callable $builder): Schema
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null && is_string($cached)) {
            try {
                return BuildSchema::build($cached);
            } catch (\Throwable) {
                // Cache corrupted, rebuild
                $this->cache->delete($cacheKey);
            }
        }

        /** @var Schema $schema */
        $schema = $builder();

        $sdl = SchemaPrinter::doPrint($schema);
        $this->cache->set($cacheKey, $sdl, $this->ttl > 0 ? $this->ttl : null);

        return $schema;
    }

    /**
     * Store a schema in the cache.
     *
     * @param string $key    Cache key identifier
     * @param Schema $schema The schema to cache
     *
     * @return void
     */
    public function put(string $key, Schema $schema): void
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $sdl = SchemaPrinter::doPrint($schema);
        $this->cache->set($cacheKey, $sdl, $this->ttl > 0 ? $this->ttl : null);
    }

    /**
     * Clear a cached schema.
     *
     * @param string $key Cache key identifier
     *
     * @return void
     */
    public function clear(string $key): void
    {
        $this->cache->delete(self::CACHE_PREFIX . $key);
    }

    /**
     * Check if a schema is cached.
     *
     * @param string $key Cache key identifier
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->cache->has(self::CACHE_PREFIX . $key);
    }
}
