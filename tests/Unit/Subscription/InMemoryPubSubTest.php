<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Subscription;

use MonkeysLegion\GraphQL\Subscription\InMemoryPubSub;
use PHPUnit\Framework\TestCase;

final class InMemoryPubSubTest extends TestCase
{
    public function testPublishAndSubscribe(): void
    {
        $pubSub = new InMemoryPubSub();
        $received = [];

        $subId = $pubSub->subscribe('channel-1', function ($payload) use (&$received) {
            $received[] = $payload;
        });

        $this->assertIsString($subId);

        $pubSub->publish('channel-1', ['msg' => 'hello']);
        $pubSub->publish('channel-1', ['msg' => 'world']);

        $this->assertCount(2, $received);
        $this->assertSame('hello', $received[0]['msg']);
        $this->assertSame('world', $received[1]['msg']);
    }

    public function testUnsubscribeStopsReceiving(): void
    {
        $pubSub = new InMemoryPubSub();
        $received = [];

        $subId = $pubSub->subscribe('ch', function ($payload) use (&$received) {
            $received[] = $payload;
        });

        $pubSub->publish('ch', 'before');
        $pubSub->unsubscribe('ch', $subId);
        $pubSub->publish('ch', 'after');

        $this->assertCount(1, $received);
        $this->assertSame('before', $received[0]);
    }

    public function testMultipleSubscribers(): void
    {
        $pubSub = new InMemoryPubSub();
        $a = [];
        $b = [];

        $pubSub->subscribe('ch', function ($p) use (&$a) { $a[] = $p; });
        $pubSub->subscribe('ch', function ($p) use (&$b) { $b[] = $p; });

        $pubSub->publish('ch', 'data');

        $this->assertCount(1, $a);
        $this->assertCount(1, $b);
    }

    public function testPublishToNonExistentChannelDoesNotError(): void
    {
        $pubSub = new InMemoryPubSub();
        $pubSub->publish('empty', 'data'); // Should not throw
        $this->assertTrue(true);
    }

    public function testUnsubscribeFromNonExistentChannelDoesNotError(): void
    {
        $pubSub = new InMemoryPubSub();
        $pubSub->unsubscribe('empty', 'fake-id'); // Should not throw
        $this->assertTrue(true);
    }
}
