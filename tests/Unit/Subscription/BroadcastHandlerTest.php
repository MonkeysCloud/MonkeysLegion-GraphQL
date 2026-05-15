<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Subscription;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Attribute\Broadcast;
use MonkeysLegion\GraphQL\Subscription\BroadcastHandler;
use MonkeysLegion\GraphQL\Subscription\InMemoryPubSub;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use GraphQL\Type\Definition\ResolveInfo;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class BroadcastHandlerTest extends TestCase
{
    private function makeContext(): GraphQLContext
    {
        return new GraphQLContext(
            $this->createMock(ServerRequestInterface::class),
            null,
            $this->createMock(ContainerInterface::class),
            new DataLoaderRegistry(),
        );
    }

    public function testBroadcastPublishesToPubSub(): void
    {
        $pubSub = new InMemoryPubSub();
        $handler = new BroadcastHandler($pubSub);

        $received = [];
        $pubSub->subscribe('posts', function ($payload) use (&$received) {
            $received[] = $payload;
        });

        $resolver = static fn() => ['id' => 1, 'title' => 'Hello'];

        $broadcasts = [new Broadcast(channel: 'posts', event: 'postCreated')];
        $wrapped = $handler->wrap($resolver, $broadcasts);

        $resolveInfo = $this->createMock(ResolveInfo::class);
        $result = $wrapped(null, [], $this->makeContext(), $resolveInfo);

        $this->assertSame(['id' => 1, 'title' => 'Hello'], $result);
        $this->assertCount(1, $received);
        $this->assertSame('postCreated', $received[0]['event']);
        $this->assertSame(['id' => 1, 'title' => 'Hello'], $received[0]['data']);
    }

    public function testNoBroadcastPassesThrough(): void
    {
        $pubSub = new InMemoryPubSub();
        $handler = new BroadcastHandler($pubSub);

        $resolver = static fn() => 'data';
        $wrapped = $handler->wrap($resolver, []);

        // Should be the exact same callable (no wrapping)
        $this->assertSame($resolver, $wrapped);
    }
}
