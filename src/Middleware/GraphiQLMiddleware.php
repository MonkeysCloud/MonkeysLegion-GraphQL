<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

final class GraphiQLMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $resp,
        private bool $enabled = true
    ) {}

    public function process(ServerRequestInterface $r, RequestHandlerInterface $h): ResponseInterface
    {
        if (!$this->enabled || $r->getUri()->getPath() !== '/graphiql') {
            return $h->handle($r);
        }

        $html = <<<HTML
<!doctype html><html><head>
  <title>GraphiQL</title>
  <link rel="stylesheet" href="https://unpkg.com/graphiql/graphiql.min.css"/>
</head><body style="margin:0;">
<div id="graphiql" style="height:100vh;"></div>
<script
  crossorigin
  src="https://unpkg.com/react/umd/react.production.min.js"></script>
<script
  crossorigin
  src="https://unpkg.com/react-dom/umd/react-dom.production.min.js"></script>
<script
  src="https://unpkg.com/graphiql/graphiql.min.js"></script>
<script>
  const fetcher = GraphiQL.createFetcher({url: '/graphql'});
  ReactDOM.render(
    React.createElement(GraphiQL, {fetcher}),
    document.getElementById('graphiql'),
  );
</script></body></html>
HTML;

        $response = $this->resp->createResponse()
            ->withHeader('Content-Type', 'text/html');
        $response->getBody()->write($html);
        return $response;
    }
}