<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Http;

use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that serves the GraphiQL IDE.
 *
 * Renders an HTML page with the GraphiQL React component configured
 * to point at the GraphQL endpoint.
 */
final class GraphiQLMiddleware implements MiddlewareInterface
{
    /**
     * @param GraphQLConfig $config Configuration for endpoint paths
     */
    public function __construct(
        private readonly GraphQLConfig $config,
    ) {}

    /**
     * Process the request and serve the GraphiQL IDE page.
     *
     * @param ServerRequestInterface  $request The incoming request
     * @param RequestHandlerInterface $handler The next handler
     *
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        $graphiqlEndpoint = $this->config->graphiqlEndpoint();

        if (rtrim($path, '/') !== rtrim($graphiqlEndpoint, '/')) {
            return $handler->handle($request);
        }

        if (!$this->config->graphiqlEnabled()) {
            return $handler->handle($request);
        }

        $endpoint = $this->config->endpoint();
        $html = $this->renderHtml($endpoint);

        // Build response
        if (class_exists(\Nyholm\Psr7\Response::class)) {
            $response = new \Nyholm\Psr7\Response(200);
        } else {
            throw new \RuntimeException(
                'No PSR-7 Response class available. Install nyholm/psr7.',
            );
        }

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Render the GraphiQL HTML page.
     *
     * @param string $endpoint The GraphQL endpoint URL
     *
     * @return string Complete HTML document
     */
    private function renderHtml(string $endpoint): string
    {
        $escapedEndpoint = htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>GraphiQL — MonkeysLegion</title>
            <link rel="stylesheet" href="https://unpkg.com/graphiql@3/graphiql.min.css" />
            <style>
                body { margin: 0; height: 100vh; overflow: hidden; }
                #graphiql { height: 100vh; }
            </style>
        </head>
        <body>
            <div id="graphiql"></div>
            <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
            <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
            <script src="https://unpkg.com/graphiql@3/graphiql.min.js" crossorigin></script>
            <script>
                const fetcher = GraphiQL.createFetcher({
                    url: '{$escapedEndpoint}',
                });
                const root = ReactDOM.createRoot(document.getElementById('graphiql'));
                root.render(React.createElement(GraphiQL, { fetcher }));
            </script>
        </body>
        </html>
        HTML;
    }
}
