<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Context;

use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

final class GraphQLContextTest extends TestCase
{
    public function testConstructAndAccessProperties(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $user = (object) ['id' => 1, 'name' => 'Alice'];
        $container = $this->createMock(ContainerInterface::class);
        $loaders = new DataLoaderRegistry();

        $ctx = new GraphQLContext($request, $user, $container, $loaders);

        $this->assertSame($request, $ctx->request);
        $this->assertSame($user, $ctx->user);
        $this->assertSame($container, $ctx->container);
        $this->assertSame($loaders, $ctx->loaders);
    }

    public function testNullUser(): void
    {
        $ctx = new GraphQLContext(
            $this->createMock(ServerRequestInterface::class),
            null,
            $this->createMock(ContainerInterface::class),
            new DataLoaderRegistry(),
        );

        $this->assertNull($ctx->user);
    }

    public function testGetDelegatestoContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $service = new \stdClass();
        $container->method('get')->with('MyService')->willReturn($service);

        $ctx = new GraphQLContext(
            $this->createMock(ServerRequestInterface::class),
            null,
            $container,
            new DataLoaderRegistry(),
        );

        $this->assertSame($service, $ctx->get('MyService'));
    }
}
