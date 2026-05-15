<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Http;

use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use MonkeysLegion\GraphQL\Http\GraphiQLMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphiQLMiddlewareTest extends TestCase
{
    public function testNonMatchingPathPassesThrough(): void
    {
        $config = new GraphQLConfig();
        $middleware = new GraphiQLMiddleware($config);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/other-page');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);
        $this->assertSame($response, $result);
    }

    public function testDisabledGraphiqlPassesThrough(): void
    {
        $config = new GraphQLConfig(['graphql.graphiql_enabled' => false]);
        $middleware = new GraphiQLMiddleware($config);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/graphiql');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);
        $this->assertSame($response, $result);
    }

    public function testMatchingPathReturnsGraphiQLHtml(): void
    {
        $config = new GraphQLConfig();
        $middleware = new GraphiQLMiddleware($config);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/graphiql');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
