<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Subscription;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Subscription\InMemoryPubSub;
use MonkeysLegion\GraphQL\Subscription\SubscriptionManager;

class SubscriptionManagerTest extends TestCase
{
    public function testSubscribeAndUnsubscribe(): void
    {
        $pubSub = new InMemoryPubSub();
        $manager = new SubscriptionManager($pubSub);

        $received = [];
        $manager->subscribe('conn-1', 'sub-1', 'posts', function ($payload) use (&$received) {
            $received[] = $payload;
        });

        $this->assertTrue($manager->hasSubscriptions('conn-1'));
        $this->assertSame(['sub-1'], $manager->getSubscriptions('conn-1'));
        $this->assertSame(1, $manager->totalSubscriptions());

        // Publish
        $pubSub->publish('posts', ['title' => 'Hello']);
        $this->assertCount(1, $received);

        // Unsubscribe
        $manager->unsubscribe('conn-1', 'sub-1');
        $this->assertFalse($manager->hasSubscriptions('conn-1'));

        $pubSub->publish('posts', ['title' => 'World']);
        $this->assertCount(1, $received); // Should NOT receive after unsub
    }

    public function testUnsubscribeAllOnDisconnect(): void
    {
        $pubSub = new InMemoryPubSub();
        $manager = new SubscriptionManager($pubSub);

        $manager->subscribe('conn-1', 'sub-1', 'posts', fn() => null);
        $manager->subscribe('conn-1', 'sub-2', 'comments', fn() => null);

        $this->assertSame(2, $manager->totalSubscriptions());

        $manager->unsubscribeAll('conn-1');

        $this->assertSame(0, $manager->totalSubscriptions());
        $this->assertFalse($manager->hasSubscriptions('conn-1'));
    }
}
