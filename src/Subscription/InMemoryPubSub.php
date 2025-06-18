<?php
namespace MonkeysLegion\GraphQL\Subscription;

/**
 * In-memory Pub/Sub implementation for testing or simple use cases.
 * This is not suitable for production use, as it does not support persistence
 * or distributed systems.
 */
final class InMemoryPubSub implements PubSubInterface
{
    /** @var array<string,list<callable>> */
    private array $topics = [];

    /**
     * Publishes a message to the specified topic.
     *
     * @param string $topic The topic to publish to.
     * @param mixed $payload The payload to send to subscribers.
     */
    public function publish(string $topic, mixed $payload): void
    {
        foreach ($this->topics[$topic] ?? [] as $listener) {
            $listener($payload);
        }
    }

    /**
     * Subscribes a listener to a topic.
     *
     * @param string $topic The topic to subscribe to.
     * @param callable $listener The listener to call when a message is published.
     * @return callable A function that can be called to unsubscribe the listener.
     */
    public function subscribe(string $topic, callable $listener): callable
    {
        $this->topics[$topic][] = $listener;
        return function () use ($topic, $listener) {
            $this->topics[$topic] =
                array_filter($this->topics[$topic] ?? [], fn ($l) => $l !== $listener);
        };
    }
}