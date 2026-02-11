<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Subscription;

/**
 * Redis-backed PubSub implementation.
 *
 * Uses the Redis pub/sub mechanism for multi-process/multi-server scenarios.
 * Requires the php-redis extension.
 */
final class RedisPubSub implements PubSubInterface
{
    /** @var \Redis|null Redis connection for publishing */
    private ?\Redis $publisher = null;

    /** @var array<string, array<string, callable>> Local callback registry */
    private array $callbacks = [];

    /** @var int Counter for subscription IDs */
    private int $idCounter = 0;

    /**
     * @param string $dsn Redis DSN (e.g., redis://127.0.0.1:6379)
     */
    public function __construct(
        private readonly string $dsn = 'redis://127.0.0.1:6379',
    ) {}

    /**
     * Publish a payload to a Redis channel.
     *
     * @param string $channel Channel/topic name
     * @param mixed  $payload The data to publish
     *
     * @return void
     */
    public function publish(string $channel, mixed $payload): void
    {
        $redis = $this->getPublisher();
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);
        $redis->publish($channel, $serialized);
    }

    /**
     * Subscribe to a channel with a callback.
     *
     * Note: In a WebSocket server context, the actual Redis subscribe
     * runs in a background loop. This registers the callback locally.
     *
     * @param string   $channel  Channel/topic name
     * @param callable $callback Callback receiving the payload
     *
     * @return string Subscription ID
     */
    public function subscribe(string $channel, callable $callback): string
    {
        $subId = 'rsub_' . (++$this->idCounter);
        $this->callbacks[$channel][$subId] = $callback;
        return $subId;
    }

    /**
     * Unsubscribe from a channel.
     *
     * @param string $channel        Channel/topic name
     * @param string $subscriptionId Subscription ID
     *
     * @return void
     */
    public function unsubscribe(string $channel, string $subscriptionId): void
    {
        unset($this->callbacks[$channel][$subscriptionId]);

        if (empty($this->callbacks[$channel])) {
            unset($this->callbacks[$channel]);
        }
    }

    /**
     * Dispatch a message received from Redis to local callbacks.
     *
     * Called by the subscription server's Redis listener.
     *
     * @param string $channel The channel the message was received on
     * @param string $message The raw message (JSON)
     *
     * @return void
     */
    public function dispatch(string $channel, string $message): void
    {
        if (!isset($this->callbacks[$channel])) {
            return;
        }

        $payload = json_decode($message, true);

        foreach ($this->callbacks[$channel] as $callback) {
            $callback($payload);
        }
    }

    /**
     * Get or create the Redis publisher connection.
     *
     * @return \Redis
     */
    private function getPublisher(): \Redis
    {
        if ($this->publisher === null) {
            $this->publisher = new \Redis();
            $parsed = parse_url($this->dsn);
            $host = $parsed['host'] ?? '127.0.0.1';
            $port = $parsed['port'] ?? 6379;
            $this->publisher->connect($host, (int) $port);

            if (isset($parsed['pass'])) {
                $this->publisher->auth($parsed['pass']);
            }
        }

        return $this->publisher;
    }
}
