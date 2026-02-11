<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Subscription;

/**
 * Manages active GraphQL subscriptions per connection.
 *
 * Tracks subscription registration and teardown for the graphql-ws protocol.
 */
final class SubscriptionManager
{
    /**
     * @var array<string, array<string, array{channel: string, subId: string}>>
     * connectionId => [subscriptionId => {channel, subId}]
     */
    private array $subscriptions = [];

    /**
     * @param PubSubInterface $pubSub PubSub implementation
     */
    public function __construct(
        private readonly PubSubInterface $pubSub,
    ) {}

    /**
     * Register a subscription for a connection.
     *
     * @param string   $connectionId   WebSocket connection identifier
     * @param string   $subscriptionId GraphQL subscription operation ID
     * @param string   $channel        PubSub channel to subscribe to
     * @param callable $callback       Callback for incoming messages
     *
     * @return void
     */
    public function subscribe(
        string $connectionId,
        string $subscriptionId,
        string $channel,
        callable $callback,
    ): void {
        $subId = $this->pubSub->subscribe($channel, $callback);

        $this->subscriptions[$connectionId][$subscriptionId] = [
            'channel' => $channel,
            'subId'   => $subId,
        ];
    }

    /**
     * Unsubscribe a specific subscription for a connection.
     *
     * @param string $connectionId   WebSocket connection identifier
     * @param string $subscriptionId GraphQL subscription operation ID
     *
     * @return void
     */
    public function unsubscribe(string $connectionId, string $subscriptionId): void
    {
        $info = $this->subscriptions[$connectionId][$subscriptionId] ?? null;
        if ($info === null) {
            return;
        }

        $this->pubSub->unsubscribe($info['channel'], $info['subId']);
        unset($this->subscriptions[$connectionId][$subscriptionId]);

        if (empty($this->subscriptions[$connectionId])) {
            unset($this->subscriptions[$connectionId]);
        }
    }

    /**
     * Unsubscribe all subscriptions for a connection (on disconnect).
     *
     * @param string $connectionId WebSocket connection identifier
     *
     * @return void
     */
    public function unsubscribeAll(string $connectionId): void
    {
        $subs = $this->subscriptions[$connectionId] ?? [];

        foreach ($subs as $info) {
            $this->pubSub->unsubscribe($info['channel'], $info['subId']);
        }

        unset($this->subscriptions[$connectionId]);
    }

    /**
     * Get all active subscription IDs for a connection.
     *
     * @param string $connectionId WebSocket connection identifier
     *
     * @return array<string>
     */
    public function getSubscriptions(string $connectionId): array
    {
        return array_keys($this->subscriptions[$connectionId] ?? []);
    }

    /**
     * Check if a connection has any active subscriptions.
     *
     * @param string $connectionId WebSocket connection identifier
     *
     * @return bool
     */
    public function hasSubscriptions(string $connectionId): bool
    {
        return !empty($this->subscriptions[$connectionId]);
    }

    /**
     * Get the total number of active subscriptions across all connections.
     *
     * @return int
     */
    public function totalSubscriptions(): int
    {
        $count = 0;
        foreach ($this->subscriptions as $subs) {
            $count += count($subs);
        }
        return $count;
    }
}