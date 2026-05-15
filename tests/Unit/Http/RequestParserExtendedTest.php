<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Http;

use MonkeysLegion\GraphQL\Http\RequestParser;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

final class RequestParserExtendedTest extends TestCase
{
    private RequestParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RequestParser();
    }

    private function mockStream(string $content): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($content);
        return $stream;
    }

    public function testParsePostJsonBody(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $request->method('getBody')->willReturn($this->mockStream(json_encode([
            'query' => '{ users { id } }',
            'variables' => ['limit' => 10],
            'operationName' => 'GetUsers',
        ])));

        $result = $this->parser->parse($request);

        $this->assertSame('{ users { id } }', $result['query']);
        $this->assertSame(['limit' => 10], $result['variables']);
        $this->assertSame('GetUsers', $result['operationName']);
    }

    public function testParsePostGraphqlContentType(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('application/graphql');
        $request->method('getBody')->willReturn($this->mockStream('{ hello }'));

        $result = $this->parser->parse($request);

        $this->assertSame('{ hello }', $result['query']);
        $this->assertSame([], $result['variables']);
    }

    public function testParsePostInvalidJsonReturnsNulls(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $request->method('getBody')->willReturn($this->mockStream('not-json'));

        $result = $this->parser->parse($request);

        $this->assertNull($result['query']);
    }

    public function testParseGetRequest(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getQueryParams')->willReturn([
            'query' => '{ users { id } }',
            'variables' => '{"limit":5}',
            'operationName' => 'GetUsers',
        ]);

        $result = $this->parser->parse($request);

        $this->assertSame('{ users { id } }', $result['query']);
        $this->assertSame(['limit' => 5], $result['variables']);
        $this->assertSame('GetUsers', $result['operationName']);
    }

    public function testParseGetWithNoVariables(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getQueryParams')->willReturn([
            'query' => '{ hello }',
        ]);

        $result = $this->parser->parse($request);

        $this->assertSame('{ hello }', $result['query']);
        $this->assertSame([], $result['variables']);
        $this->assertNull($result['operationName']);
    }

    public function testParseUnsupportedMethodReturnsDefaults(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('PUT');

        $result = $this->parser->parse($request);

        $this->assertNull($result['query']);
        $this->assertSame([], $result['variables']);
    }

    public function testIsBatchTrue(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $request->method('getBody')->willReturn($this->mockStream(json_encode([
            ['query' => '{ a }'],
            ['query' => '{ b }'],
        ])));

        $this->assertTrue($this->parser->isBatch($request));
    }

    public function testIsBatchFalse(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $request->method('getBody')->willReturn($this->mockStream(json_encode([
            'query' => '{ a }',
        ])));

        $this->assertFalse($this->parser->isBatch($request));
    }

    public function testParseBatch(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $request->method('getBody')->willReturn($this->mockStream(json_encode([
            ['query' => '{ a }', 'variables' => []],
            ['query' => '{ b }', 'variables' => ['x' => 1]],
        ])));

        $result = $this->parser->parseBatch($request);

        $this->assertCount(2, $result);
        $this->assertSame('{ a }', $result[0]['query']);
        $this->assertSame('{ b }', $result[1]['query']);
        $this->assertSame(['x' => 1], $result[1]['variables']);
    }
}
