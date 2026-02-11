<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Security;

use Psr\SimpleCache\CacheInterface;

/**
 * Per-client rate limiter for GraphQL requests.
 *
 * Uses a sliding window counter stored in PSR-16 cache.
 * Each client is identified by IP address or a custom identifier.
 */
final class RateLimiter
{
    /** @var string Cache key prefix */
    private const CACHE_PREFIX = 'ml_graphql_rl_';

    /**
     * @param CacheInterface $cache         PSR-16 cache for storing counters
     * @param int            $maxRequests   Maximum requests per window
     * @param int            $windowSeconds Window duration in seconds
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $maxRequests = 100,
        private readonly int $windowSeconds = 60,
    ) {}

    /**
     * Check if a client has exceeded the rate limit.
     *
     * @param string $clientId Client identifier (e.g., IP address)
     *
     * @return bool True if the request is allowed, false if rate-limited
     */
    public function isAllowed(string $clientId): bool
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $clientId);
        $data = $this->cache->get($cacheKey);

        $now = time();

        if ($data === null || !is_array($data)) {
            $data = [
                'count'    => 0,
                'windowStart' => $now,
            ];
        }

        // Check if the window has expired
        if ($now - $data['windowStart'] >= $this->windowSeconds) {
            $data = [
                'count'    => 0,
                'windowStart' => $now,
            ];
        }

        if ($data['count'] >= $this->maxRequests) {
            return false;
        }

        $data['count']++;
        $ttl = $this->windowSeconds - ($now - $data['windowStart']);
        $this->cache->set($cacheKey, $data, max($ttl, 1));

        return true;
    }

    /**
     * Get the remaining request count for a client.
     *
     * @param string $clientId Client identifier
     *
     * @return int Remaining requests in the current window
     */
    public function remaining(string $clientId): int
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $clientId);
        $data = $this->cache->get($cacheKey);

        if ($data === null || !is_array($data)) {
            return $this->maxRequests;
        }

        $now = time();
        if ($now - $data['windowStart'] >= $this->windowSeconds) {
            return $this->maxRequests;
        }

        return max(0, $this->maxRequests - (int) $data['count']);
    }

    /**
     * Get the time in seconds until the rate limit window resets.
     *
     * @param string $clientId Client identifier
     *
     * @return int Seconds until reset
     */
    public function retryAfter(string $clientId): int
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $clientId);
        $data = $this->cache->get($cacheKey);

        if ($data === null || !is_array($data)) {
            return 0;
        }

        $now = time();
        $elapsed = $now - $data['windowStart'];

        if ($elapsed >= $this->windowSeconds) {
            return 0;
        }

        return $this->windowSeconds - $elapsed;
    }

    /**
     * Reset the rate limit for a client.
     *
     * @param string $clientId Client identifier
     *
     * @return void
     */
    public function reset(string $clientId): void
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $clientId);
        $this->cache->delete($cacheKey);
    }
}
