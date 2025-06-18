<?php
namespace MonkeysLegion\GraphQL\Subscription;

/**
 * In-memory Pub/Sub implementation for testing or simple use cases.
 * This is not suitable for production use, as it does not support persistence
 * or distributed systems.
 */
interface PubSubInterface
{
    /**
     * Publishes a message to the specified topic.
     *
     * @param string $topic The topic to publish to.
     * @param mixed $payload The payload to send to subscribers.
     */
    public function publish(string $topic, mixed $payload): void;

    /**
     * Subscribes a listener to a topic.
     *
     * @param string $topic The topic to subscribe to.
     * @param callable $listener The listener to call when a message is published.
     * @return callable A function that can be called to unsubscribe the listener.
     */
    public function subscribe(string $topic, callable $listener): callable;
}