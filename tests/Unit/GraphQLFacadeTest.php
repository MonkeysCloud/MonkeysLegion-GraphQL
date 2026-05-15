<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit;

use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use MonkeysLegion\GraphQL\GraphQL;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class GraphQLFacadeTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset the static container
        $ref = new \ReflectionClass(GraphQL::class);
        $prop = $ref->getProperty('container');
        $prop->setValue(null, null);
    }

    public function testSetAndGetConfig(): void
    {
        $config = new GraphQLConfig(['graphql.endpoint' => '/api']);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with(GraphQLConfig::class)
            ->willReturn($config);

        GraphQL::setContainer($container);

        $this->assertSame($config, GraphQL::config());
        $this->assertSame('/api', GraphQL::config()->endpoint());
    }

    public function testResolveThrowsWithoutContainer(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires a container');
        GraphQL::config();
    }

    public function testPublishDelegatesToPubSub(): void
    {
        $published = [];

        $pubSub = new class($published) implements \MonkeysLegion\GraphQL\Subscription\PubSubInterface {
            public function __construct(private array &$data) {}
            public function publish(string $channel, mixed $payload): void { $this->data[] = [$channel, $payload]; }
            public function subscribe(string $channel, callable $handler): string { return ''; }
            public function unsubscribe(string $channel, string $subscriptionId): void {}
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with(\MonkeysLegion\GraphQL\Subscription\PubSubInterface::class)
            ->willReturn($pubSub);

        GraphQL::setContainer($container);
        GraphQL::publish('test-channel', ['msg' => 'hello']);

        $this->assertCount(1, $published);
        $this->assertSame('test-channel', $published[0][0]);
        $this->assertSame(['msg' => 'hello'], $published[0][1]);
    }
}
