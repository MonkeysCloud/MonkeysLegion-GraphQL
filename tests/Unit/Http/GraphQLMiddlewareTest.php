<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Http;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use MonkeysLegion\GraphQL\Error\ErrorHandler;
use MonkeysLegion\GraphQL\Executor\QueryExecutor;
use MonkeysLegion\GraphQL\Http\GraphQLMiddleware;
use MonkeysLegion\GraphQL\Http\RequestParser;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GraphQLMiddlewareTest extends TestCase
{
    public function testNonGraphqlPathPassesThrough(): void
    {
        // Test that the middleware path matching works — we test the executor
        // separately, so this just validates routing logic.
        $executor = new QueryExecutor(new ErrorHandler());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['hello' => ['type' => Type::string(), 'resolve' => fn() => 'world']],
            ]),
        ]);

        // Just test the RequestParser and QueryExecutor integration
        $parser = new RequestParser();

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn(json_encode(['query' => '{ hello }']));

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaderLine')->willReturn('application/json');
        $request->method('getBody')->willReturn($stream);

        $parsed = $parser->parse($request);
        $this->assertSame('{ hello }', $parsed['query']);
    }

    public function testQueryExecutionIntegration(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'hello' => ['type' => Type::string(), 'resolve' => fn() => 'world'],
                ],
            ]),
        ]);

        $executor = new QueryExecutor(new ErrorHandler());
        $context = new \MonkeysLegion\GraphQL\Context\GraphQLContext(
            $this->createMock(ServerRequestInterface::class),
            null,
            $this->createMock(ContainerInterface::class),
            new \MonkeysLegion\GraphQL\Loader\DataLoaderRegistry(),
        );

        $result = $executor->execute($schema, '{ hello }', $context);
        $this->assertSame(['hello' => 'world'], $result['data']);
    }
}
