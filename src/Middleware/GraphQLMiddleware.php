<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Middleware;

use MonkeysLegion\GraphQL\Execution\Executor;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

final class GraphQLMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Executor $exe,
        private ResponseFactoryInterface $resp
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== '/graphql') {
            return $handler->handle($request);
        }

        $data = $this->exe->execute($request);

        $response = $this->resp->createResponse()
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $response;
    }
}