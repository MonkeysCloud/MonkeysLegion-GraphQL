<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Security;

use Psr\SimpleCache\CacheInterface;

/**
 * Automatic Persisted Queries (APQ) implementation.
 *
 * Clients send a SHA256 hash instead of the full query string.
 * If the query is cached, it's executed. If not, the client sends
 * both hash and query, which gets stored and executed.
 */
final class PersistedQueries
{
    /** @var string Cache key prefix */
    private const CACHE_PREFIX = 'ml_graphql_pq_';

    /**
     * @param CacheInterface $cache PSR-16 cache for storing queries
     * @param int            $ttl   Time-to-live in seconds (0 = forever)
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl = 86400,
    ) {}

    /**
     * Process an APQ request.
     *
     * Returns the resolved query string, or throws if the query was not
     * found and no query was provided.
     *
     * @param string|null          $query      The query string (may be null for hash-only requests)
     * @param array<string, mixed> $extensions Request extensions
     *
     * @return string The resolved query string
     *
     * @throws PersistedQueryNotFoundError If hash is not cached and no query provided
     */
    public function process(?string $query, array $extensions): string
    {
        $persistedQuery = $extensions['persistedQuery'] ?? null;

        if (!is_array($persistedQuery)) {
            if ($query === null) {
                throw new \RuntimeException('No query string provided.');
            }
            return $query;
        }

        $sha256Hash = $persistedQuery['sha256Hash'] ?? null;
        $version = $persistedQuery['version'] ?? 1;

        if (!is_string($sha256Hash) || $version !== 1) {
            if ($query === null) {
                throw new \RuntimeException('Invalid persisted query format.');
            }
            return $query;
        }

        $cacheKey = self::CACHE_PREFIX . $sha256Hash;

        // Try to find the cached query
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null && is_string($cached)) {
            return $cached;
        }

        // Query not found in cache
        if ($query === null) {
            throw new PersistedQueryNotFoundError();
        }

        // Verify hash matches the query
        $computedHash = hash('sha256', $query);
        if ($computedHash !== $sha256Hash) {
            throw new \RuntimeException('Persisted query hash mismatch.');
        }

        // Store and return
        $this->cache->set($cacheKey, $query, $this->ttl > 0 ? $this->ttl : null);

        return $query;
    }

    /**
     * Manually register a persisted query.
     *
     * @param string $hash  SHA256 hash
     * @param string $query The query string
     *
     * @return void
     */
    public function register(string $hash, string $query): void
    {
        $cacheKey = self::CACHE_PREFIX . $hash;
        $this->cache->set($cacheKey, $query, $this->ttl > 0 ? $this->ttl : null);
    }

    /**
     * Clear a persisted query from the cache.
     *
     * @param string $hash SHA256 hash
     *
     * @return void
     */
    public function clear(string $hash): void
    {
        $this->cache->delete(self::CACHE_PREFIX . $hash);
    }
}

/**
 * Error thrown when a persisted query hash is not found in the cache.
 */
final class PersistedQueryNotFoundError extends \RuntimeException
{
    /**
     * @param string $message Error message
     */
    public function __construct(string $message = 'PersistedQueryNotFound')
    {
        parent::__construct($message);
    }

    /**
     * Format as a GraphQL error response.
     *
     * @return array{message: string, extensions: array{code: string}}
     */
    public function toGraphQLError(): array
    {
        return [
            'message'    => $this->getMessage(),
            'extensions' => [
                'code' => 'PERSISTED_QUERY_NOT_FOUND',
            ],
        ];
    }
}
