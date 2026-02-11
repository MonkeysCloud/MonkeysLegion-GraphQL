<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Subscription;

use MonkeysLegion\GraphQL\Subscription\InMemoryPubSub;
use PHPUnit\Framework\TestCase;

final class PubSubTest extends TestCase
{
    public function testPublishAndSubscribe(): void
    {
        $pubsub = new InMemoryPubSub();
        $received = [];

        $subId = $pubsub->subscribe('chat', static function (mixed $payload) use (&$received): void {
            $received[] = $payload;
        });

        $pubsub->publish('chat', ['message' => 'Hello']);
        $pubsub->publish('chat', ['message' => 'World']);

        self::assertCount(2, $received);
        self::assertSame('Hello', $received[0]['message']);
        self::assertSame('World', $received[1]['message']);
        self::assertNotEmpty($subId);
    }

    public function testUnsubscribe(): void
    {
        $pubsub = new InMemoryPubSub();
        $count = 0;

        $subId = $pubsub->subscribe('events', static function () use (&$count): void {
            $count++;
        });

        $pubsub->publish('events', 'first');
        self::assertSame(1, $count);

        // unsubscribe requires both channel and subscriptionId
        $pubsub->unsubscribe('events', $subId);
        $pubsub->publish('events', 'second');
        self::assertSame(1, $count); // Should not increment
    }

    public function testMultipleSubscribers(): void
    {
        $pubsub = new InMemoryPubSub();
        $results = ['a' => 0, 'b' => 0];

        $pubsub->subscribe('topic', static function () use (&$results): void {
            $results['a']++;
        });
        $pubsub->subscribe('topic', static function () use (&$results): void {
            $results['b']++;
        });

        $pubsub->publish('topic', 'data');

        self::assertSame(1, $results['a']);
        self::assertSame(1, $results['b']);
    }

    public function testPublishToUnsubscribedChannel(): void
    {
        $pubsub = new InMemoryPubSub();
        // Should not throw
        $pubsub->publish('nonexistent', 'data');
        $this->addToAssertionCount(1);
    }

    public function testSubscriberCount(): void
    {
        $pubsub = new InMemoryPubSub();
        self::assertSame(0, $pubsub->subscriberCount('test'));

        $pubsub->subscribe('test', static fn() => null);
        $pubsub->subscribe('test', static fn() => null);
        self::assertSame(2, $pubsub->subscriberCount('test'));
    }

    public function testChannelsList(): void
    {
        $pubsub = new InMemoryPubSub();
        self::assertEmpty($pubsub->channels());

        $pubsub->subscribe('a', static fn() => null);
        $pubsub->subscribe('b', static fn() => null);
        self::assertCount(2, $pubsub->channels());
        self::assertContains('a', $pubsub->channels());
        self::assertContains('b', $pubsub->channels());
    }
}
