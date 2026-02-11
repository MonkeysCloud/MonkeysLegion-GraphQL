<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Http;

use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Executor\BatchExecutor;
use MonkeysLegion\GraphQL\Executor\QueryExecutor;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use MonkeysLegion\GraphQL\Security\ComplexityAnalyzer;
use MonkeysLegion\GraphQL\Security\DepthLimiter;
use MonkeysLegion\GraphQL\Security\IntrospectionControl;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that handles GraphQL HTTP requests.
 *
 * Matches the configured GraphQL endpoint, parses the request, creates
 * the execution context, runs security validators, executes the query,
 * and returns a JSON response.
 */
final class GraphQLMiddleware implements MiddlewareInterface
{
    /** @var \GraphQL\Type\Schema|null Cached schema instance */
    private ?\GraphQL\Type\Schema $schema = null;

    /**
     * @param GraphQLConfig      $config        Configuration
     * @param SchemaBuilder      $schemaBuilder Schema builder
     * @param QueryExecutor      $executor      Query executor
     * @param RequestParser      $parser        Request parser
     * @param ContainerInterface $container     DI container
     */
    public function __construct(
        private readonly GraphQLConfig $config,
        private readonly SchemaBuilder $schemaBuilder,
        private readonly QueryExecutor $executor,
        private readonly RequestParser $parser,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Process a GraphQL request.
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
        $endpoint = $this->config->endpoint();

        // Only handle requests matching the GraphQL endpoint
        if (rtrim($path, '/') !== rtrim($endpoint, '/')) {
            return $handler->handle($request);
        }

        // Handle OPTIONS (CORS preflight)
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->createCorsResponse($request);
        }

        // Only allow GET and POST
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['GET', 'POST'], true)) {
            return $this->createJsonResponse(
                ['errors' => [['message' => 'Method not allowed. Use GET or POST.']]],
                405,
            );
        }

        // Build or retrieve cached schema
        $schema = $this->getSchema();

        // Create context
        $context = $this->createContext($request);

        // Build validation rules
        $validationRules = $this->buildValidationRules();

        // Check for batch requests
        if ($this->parser->isBatch($request)) {
            $operations = $this->parser->parseBatch($request);
            $results = [];

            foreach ($operations as $operation) {
                if ($operation['query'] === null) {
                    $results[] = ['errors' => [['message' => 'No query string provided.']]];
                    continue;
                }

                $results[] = $this->executor->execute(
                    $schema,
                    $operation['query'],
                    $context,
                    $operation['variables'] ?: null,
                    $operation['operationName'],
                    $validationRules,
                );
            }

            return $this->createJsonResponse($results);
        }

        // Single request
        $parsed = $this->parser->parse($request);

        if ($parsed['query'] === null) {
            return $this->createJsonResponse(
                ['errors' => [['message' => 'No query string provided.']]],
                400,
            );
        }

        $result = $this->executor->execute(
            $schema,
            $parsed['query'],
            $context,
            $parsed['variables'] ?: null,
            $parsed['operationName'],
            $validationRules,
        );

        return $this->createJsonResponse($result);
    }

    /**
     * Get or build the GraphQL schema.
     *
     * @return \GraphQL\Type\Schema
     */
    private function getSchema(): \GraphQL\Type\Schema
    {
        if ($this->schema === null) {
            $this->schema = $this->schemaBuilder->build($this->config->scanDirectories());
        }

        return $this->schema;
    }

    /**
     * Create a GraphQL execution context from the request.
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return GraphQLContext
     */
    private function createContext(ServerRequestInterface $request): GraphQLContext
    {
        // Try to extract user from request attributes (set by auth middleware)
        $user = $request->getAttribute('user');
        $userObj = is_object($user) ? $user : null;

        $loaders = $this->container->has(DataLoaderRegistry::class)
            ? $this->container->get(DataLoaderRegistry::class)
            : new DataLoaderRegistry();

        return new GraphQLContext(
            request: $request,
            user: $userObj,
            container: $this->container,
            loaders: $loaders,
        );
    }

    /**
     * Build the list of validation rules based on configuration.
     *
     * @return array<\GraphQL\Validator\Rules\ValidationRule>
     */
    private function buildValidationRules(): array
    {
        $rules = [];

        $maxDepth = $this->config->maxDepth();
        if ($maxDepth > 0) {
            $rules[] = new DepthLimiter($maxDepth);
        }

        $maxComplexity = $this->config->maxComplexity();
        if ($maxComplexity > 0) {
            $rules[] = new ComplexityAnalyzer($maxComplexity);
        }

        if (!$this->config->introspectionEnabled()) {
            $rules[] = new IntrospectionControl(false);
        }

        return $rules;
    }

    /**
     * Create a JSON PSR-7 response.
     *
     * @param mixed $data       Response data
     * @param int   $statusCode HTTP status code
     *
     * @return ResponseInterface
     */
    private function createJsonResponse(mixed $data, int $statusCode = 200): ResponseInterface
    {
        /** @var \Psr\Http\Message\ResponseFactoryInterface|null $factory */
        $factory = $this->container->has(\Psr\Http\Message\ResponseFactoryInterface::class)
            ? $this->container->get(\Psr\Http\Message\ResponseFactoryInterface::class)
            : null;

        if ($factory !== null) {
            $response = $factory->createResponse($statusCode);
        } else {
            // Fallback: create a basic response using nyholm/psr7 if available
            if (class_exists(\Nyholm\Psr7\Response::class)) {
                $response = new \Nyholm\Psr7\Response($statusCode);
            } else {
                throw new \RuntimeException(
                    'No PSR-7 ResponseFactory found. Install nyholm/psr7 or register a ResponseFactoryInterface.',
                );
            }
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if ($response->getBody()->isWritable()) {
            $response->getBody()->write($json);
        } else {
            /** @var \Psr\Http\Message\StreamFactoryInterface|null $streamFactory */
            $streamFactory = $this->container->has(\Psr\Http\Message\StreamFactoryInterface::class)
                ? $this->container->get(\Psr\Http\Message\StreamFactoryInterface::class)
                : null;

            if ($streamFactory !== null) {
                $stream = $streamFactory->createStream($json);
                $response = $response->withBody($stream);
            }
        }

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Access-Control-Allow-Origin', '*');
    }

    /**
     * Create a CORS preflight response.
     *
     * @param ServerRequestInterface $request The incoming request
     *
     * @return ResponseInterface
     */
    private function createCorsResponse(ServerRequestInterface $request): ResponseInterface
    {
        return $this->createJsonResponse(null, 204)
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
