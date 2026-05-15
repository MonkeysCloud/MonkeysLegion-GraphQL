<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Upload;

use MonkeysLegion\GraphQL\Upload\UploadMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class UploadMiddlewareTest extends TestCase
{
    public function testNonMultipartPassesThrough(): void
    {
        $middleware = new UploadMiddleware();

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');

        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);
        $this->assertSame($response, $result);
    }

    public function testMultipartWithoutOperationsPassesThrough(): void
    {
        $middleware = new UploadMiddleware();

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('multipart/form-data');
        $request->method('getParsedBody')->willReturn(['no_operations' => true]);

        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);
        $this->assertSame($response, $result);
    }

    public function testMultipartWithNonArrayBodyPassesThrough(): void
    {
        $middleware = new UploadMiddleware();

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('multipart/form-data');
        $request->method('getParsedBody')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($response);

        $result = $middleware->process($request, $handler);
        $this->assertSame($response, $result);
    }
}
