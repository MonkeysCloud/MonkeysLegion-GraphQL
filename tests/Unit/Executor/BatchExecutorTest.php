<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Executor;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Error\ErrorHandler;
use MonkeysLegion\GraphQL\Executor\BatchExecutor;
use MonkeysLegion\GraphQL\Executor\QueryExecutor;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BatchExecutorTest extends TestCase
{
    private Schema $schema;
    private GraphQLContext $context;

    protected function setUp(): void
    {
        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'hello' => ['type' => Type::string(), 'resolve' => fn() => 'world'],
                    'greet' => [
                        'type' => Type::string(),
                        'args' => ['name' => ['type' => Type::nonNull(Type::string())]],
                        'resolve' => fn($root, $args) => "Hi, {$args['name']}",
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

    public function testExecuteMultipleOperations(): void
    {
        $executor = new BatchExecutor(new QueryExecutor(new ErrorHandler()));

        $results = $executor->execute($this->schema, $this->context, [
            ['query' => '{ hello }'],
            ['query' => 'query G($n: String!) { greet(name: $n) }', 'variables' => ['n' => 'Alice']],
        ]);

        $this->assertCount(2, $results);
        $this->assertSame(['hello' => 'world'], $results[0]['data']);
        $this->assertSame(['greet' => 'Hi, Alice'], $results[1]['data']);
    }

    public function testExecuteWithNullQueryReturnsError(): void
    {
        $executor = new BatchExecutor(new QueryExecutor(new ErrorHandler()));

        $results = $executor->execute($this->schema, $this->context, [
            ['query' => null],
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('No query string provided.', $results[0]['errors'][0]['message']);
    }

    public function testEmptyBatchReturnsEmpty(): void
    {
        $executor = new BatchExecutor(new QueryExecutor(new ErrorHandler()));
        $results = $executor->execute($this->schema, $this->context, []);
        $this->assertSame([], $results);
    }
}
