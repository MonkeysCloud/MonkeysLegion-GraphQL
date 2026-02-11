<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Subscription;

/**
 * Interface for publish/subscribe implementations.
 *
 * Abstracts the PubSub mechanism so that in-memory,
 * Redis, or other implementations can be swapped.
 */
interface PubSubInterface
{
    /**
     * Publish a payload to a channel.
     *
     * @param string $channel Channel/topic name
     * @param mixed  $payload The data to publish
     *
     * @return void
     */
    public function publish(string $channel, mixed $payload): void;

    /**
     * Subscribe to a channel with a callback.
     *
     * @param string   $channel  Channel/topic name
     * @param callable $callback Callback receiving the payload
     *
     * @return string Subscription ID for later unsubscribe
     */
    public function subscribe(string $channel, callable $callback): string;

    /**
     * Unsubscribe from a channel.
     *
     * @param string $channel        Channel/topic name
     * @param string $subscriptionId Subscription ID from subscribe()
     *
     * @return void
     */
    public function unsubscribe(string $channel, string $subscriptionId): void;
}