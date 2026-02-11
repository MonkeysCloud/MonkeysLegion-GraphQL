<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Http;

use MonkeysLegion\GraphQL\Http\RequestParser;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;

final class RequestParserTest extends TestCase
{
    private RequestParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RequestParser();
    }

    public function testParseGetRequest(): void
    {
        $request = new ServerRequest('GET', '/graphql');
        $request = $request->withQueryParams([
            'query' => '{ users { name } }',
        ]);

        $result = $this->parser->parse($request);
        self::assertSame('{ users { name } }', $result['query']);
    }

    public function testParsePostJsonRequest(): void
    {
        $body = json_encode([
            'query'     => '{ users { name } }',
            'variables' => ['id' => 1],
        ]);

        $request = new ServerRequest('POST', '/graphql', [
            'Content-Type' => 'application/json',
        ]);
        $request = $request->withBody(Stream::create($body));

        $result = $this->parser->parse($request);
        self::assertSame('{ users { name } }', $result['query']);
        self::assertSame(['id' => 1], $result['variables']);
    }

    public function testParseApplicationGraphqlRequest(): void
    {
        $query = '{ users { name } }';
        $request = new ServerRequest('POST', '/graphql', [
            'Content-Type' => 'application/graphql',
        ]);
        $request = $request->withBody(Stream::create($query));

        $result = $this->parser->parse($request);
        self::assertSame($query, $result['query']);
    }

    public function testParseBatchRequest(): void
    {
        $body = json_encode([
            ['query' => '{ users { name } }'],
            ['query' => '{ users { email } }'],
        ]);

        $request = new ServerRequest('POST', '/graphql', [
            'Content-Type' => 'application/json',
        ]);
        $request = $request->withBody(Stream::create($body));

        // parseBatch detects arrays of operations
        $result = $this->parser->parseBatch($request);
        self::assertCount(2, $result);
        self::assertSame('{ users { name } }', $result[0]['query']);
        self::assertSame('{ users { email } }', $result[1]['query']);
    }

    public function testIsBatchDetection(): void
    {
        $batch = json_encode([
            ['query' => '{ a }'],
            ['query' => '{ b }'],
        ]);

        $request = new ServerRequest('POST', '/graphql', [
            'Content-Type' => 'application/json',
        ]);
        $request = $request->withBody(Stream::create($batch));
        self::assertTrue($this->parser->isBatch($request));

        $single = json_encode(['query' => '{ a }']);
        $request2 = new ServerRequest('POST', '/graphql', [
            'Content-Type' => 'application/json',
        ]);
        $request2 = $request2->withBody(Stream::create($single));
        self::assertFalse($this->parser->isBatch($request2));
    }
}
