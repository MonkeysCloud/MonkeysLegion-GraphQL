<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Subscription;

/**
 * In-memory PubSub implementation.
 *
 * Suitable for single-process/testing scenarios.
 * Subscriptions do not persist between requests.
 */
final class InMemoryPubSub implements PubSubInterface
{
    /** @var array<string, array<string, callable>> channel => [subId => callback] */
    private array $subscriptions = [];

    /** @var int Counter for generating unique subscription IDs */
    private int $idCounter = 0;

    /**
     * Publish a payload to all subscribers of a channel.
     *
     * @param string $channel Channel/topic name
     * @param mixed  $payload The data to publish
     *
     * @return void
     */
    public function publish(string $channel, mixed $payload): void
    {
        if (!isset($this->subscriptions[$channel])) {
            return;
        }

        foreach ($this->subscriptions[$channel] as $callback) {
            $callback($payload);
        }
    }

    /**
     * Subscribe to a channel.
     *
     * @param string   $channel  Channel/topic name
     * @param callable $callback Callback receiving the payload
     *
     * @return string Subscription ID
     */
    public function subscribe(string $channel, callable $callback): string
    {
        $subId = 'sub_' . (++$this->idCounter);
        $this->subscriptions[$channel][$subId] = $callback;
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
        unset($this->subscriptions[$channel][$subscriptionId]);

        if (empty($this->subscriptions[$channel])) {
            unset($this->subscriptions[$channel]);
        }
    }

    /**
     * Get the number of subscribers for a channel.
     *
     * @param string $channel Channel name
     *
     * @return int
     */
    public function subscriberCount(string $channel): int
    {
        return count($this->subscriptions[$channel] ?? []);
    }

    /**
     * Get all channel names with active subscriptions.
     *
     * @return array<string>
     */
    public function channels(): array
    {
        return array_keys($this->subscriptions);
    }
}