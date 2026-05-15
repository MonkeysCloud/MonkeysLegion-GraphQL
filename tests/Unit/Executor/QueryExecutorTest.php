<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Executor;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Error\ErrorHandler;
use MonkeysLegion\GraphQL\Executor\QueryExecutor;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

final class QueryExecutorTest extends TestCase
{
    private Schema $schema;
    private GraphQLContext $context;

    protected function setUp(): void
    {
        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'hello' => [
                        'type' => Type::string(),
                        'resolve' => fn() => 'world',
                    ],
                    'greet' => [
                        'type' => Type::string(),
                        'args' => [
                            'name' => ['type' => Type::nonNull(Type::string())],
                        ],
                        'resolve' => fn($root, $args) => "Hello, {$args['name']}!",
                    ],
                    'error' => [
                        'type' => Type::string(),
                        'resolve' => fn() => throw new \RuntimeException('boom'),
                    ],
                ],
            ]),
        ]);

        $this->context = new GraphQLContext(
            $this->createMock(ServerRequestInterface::class),
            null,
            $this->createMock(ContainerInterface::class),
            new DataLoaderRegistry(),
        );
    }

    public function testExecuteSimpleQuery(): void
    {
        $executor = new QueryExecutor(new ErrorHandler());
        $result = $executor->execute($this->schema, '{ hello }', $this->context);

        $this->assertSame(['hello' => 'world'], $result['data']);
        $this->assertArrayNotHasKey('errors', $result);
    }

    public function testExecuteWithVariables(): void
    {
        $executor = new QueryExecutor(new ErrorHandler());
        $result = $executor->execute(
            $this->schema,
            'query Greet($name: String!) { greet(name: $name) }',
            $this->context,
            ['name' => 'Jorge'],
        );

        $this->assertSame(['greet' => 'Hello, Jorge!'], $result['data']);
    }

    public function testExecuteReturnsErrorsInProduction(): void
    {
        $executor = new QueryExecutor(new ErrorHandler(debug: false));
        $result = $executor->execute($this->schema, '{ error }', $this->context);

        $this->assertNull($result['data']['error']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame('Internal server error', $result['errors'][0]['message']);
    }

    public function testExecuteReturnsDebugInfoInDevMode(): void
    {
        $executor = new QueryExecutor(new ErrorHandler(debug: true));
        $result = $executor->execute($this->schema, '{ error }', $this->context);

        $this->assertNotEmpty($result['errors']);
        $this->assertSame('boom', $result['errors'][0]['extensions']['debugMessage']);
    }

    public function testExecuteRawReturnsExecutionResult(): void
    {
        $executor = new QueryExecutor(new ErrorHandler());
        $result = $executor->executeRaw($this->schema, '{ hello }', $this->context);

        $this->assertInstanceOf(\GraphQL\Executor\ExecutionResult::class, $result);
        $this->assertSame('world', $result->data['hello']);
    }

    public function testExecuteWithValidationRules(): void
    {
        $executor = new QueryExecutor(new ErrorHandler());

        // Depth of 1 should be fine for { hello }
        $depthRule = new \MonkeysLegion\GraphQL\Security\DepthLimiter(1);
        $result = $executor->execute($this->schema, '{ hello }', $this->context, validationRules: [$depthRule]);

        $this->assertSame(['hello' => 'world'], $result['data']);
    }

    public function testExecuteSyntaxError(): void
    {
        $executor = new QueryExecutor(new ErrorHandler());
        $result = $executor->execute($this->schema, '{ invalid!!!', $this->context);

        $this->assertNotEmpty($result['errors']);
    }
}
