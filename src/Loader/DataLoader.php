<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Loader;

/**
 * Abstract DataLoader implementing the batch-loading pattern.
 *
 * Collects individual load requests, batches them, and caches results
 * per-request to prevent N+1 query problems.
 *
 * Subclasses must implement batchLoad() with the actual data fetching logic.
 */
abstract class DataLoader
{
    /** @var array<int|string> Pending keys to load */
    private array $queue = [];

    /** @var array<int|string, mixed> Per-request cache of loaded values */
    private array $cache = [];

    /** @var array<int|string, array<callable>> Pending callbacks waiting for resolution */
    private array $pendingCallbacks = [];

    /**
     * Load values for a batch of keys.
     *
     * Must return an array with the same length as $keys, in the same order.
     * Use null for keys that don't have corresponding values.
     *
     * @param array<int|string> $keys The keys to load
     *
     * @return array<mixed> Values in the same order as keys
     */
    abstract protected function batchLoad(array $keys): array;

    /**
     * Request loading of a single key.
     *
     * The value will be available after flush() is called.
     * If the key is already cached, returns the cached value immediately.
     *
     * @param int|string $key The key to load
     *
     * @return mixed The cached value if available, or null (value available after flush)
     */
    public function load(int|string $key): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if (!in_array($key, $this->queue, true)) {
            $this->queue[] = $key;
        }

        return null;
    }

    /**
     * Load a key and register a callback to receive the value.
     *
     * @param int|string $key      The key to load
     * @param callable   $callback Callback receiving the loaded value
     *
     * @return void
     */
    public function loadDeferred(int|string $key, callable $callback): void
    {
        if (array_key_exists($key, $this->cache)) {
            $callback($this->cache[$key]);
            return;
        }

        if (!in_array($key, $this->queue, true)) {
            $this->queue[] = $key;
        }

        $this->pendingCallbacks[$key] ??= [];
        $this->pendingCallbacks[$key][] = $callback;
    }

    /**
     * Request loading of multiple keys at once.
     *
     * @param array<int|string> $keys The keys to load
     *
     * @return array<int|string, mixed> Cached values (subset — uncached keys return null)
     */
    public function loadMany(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->load($key);
        }
        return $results;
    }

    /**
     * Flush the queue: batch-load all pending keys and cache the results.
     *
     * @return void
     */
    public function flush(): void
    {
        $keys = array_unique($this->queue);
        $this->queue = [];

        if ($keys === []) {
            return;
        }

        // Re-index keys for consistent mapping
        $keys = array_values($keys);
        $results = $this->batchLoad($keys);

        foreach ($keys as $i => $key) {
            $value = $results[$i] ?? null;
            $this->cache[$key] = $value;

            // Resolve pending callbacks
            if (isset($this->pendingCallbacks[$key])) {
                foreach ($this->pendingCallbacks[$key] as $callback) {
                    $callback($value);
                }
                unset($this->pendingCallbacks[$key]);
            }
        }
    }

    /**
     * Get a cached value without queueing.
     *
     * @param int|string $key The key to look up
     *
     * @return mixed|null The cached value or null if not cached
     */
    public function getCached(int|string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    /**
     * Check if a key is in the cache.
     *
     * @param int|string $key The key to check
     *
     * @return bool
     */
    public function isCached(int|string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * Prime the cache with a value (bypass loading).
     *
     * @param int|string $key   The key
     * @param mixed      $value The value to cache
     *
     * @return void
     */
    public function prime(int|string $key, mixed $value): void
    {
        $this->cache[$key] = $value;
    }

    /**
     * Clear a specific key from the cache.
     *
     * @param int|string $key The key to clear
     *
     * @return void
     */
    public function clear(int|string $key): void
    {
        unset($this->cache[$key]);
    }

    /**
     * Clear the entire cache and queue.
     *
     * @return void
     */
    public function clearAll(): void
    {
        $this->cache = [];
        $this->queue = [];
        $this->pendingCallbacks = [];
    }

    /**
     * Get the number of pending keys in the queue.
     *
     * @return int
     */
    public function queueSize(): int
    {
        return count($this->queue);
    }
}
