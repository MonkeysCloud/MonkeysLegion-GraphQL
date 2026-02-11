<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Integration;

use GraphQL\Type\Schema;
use MonkeysLegion\GraphQL\Builder\ArgumentBuilder;
use MonkeysLegion\GraphQL\Builder\EnumBuilder;
use MonkeysLegion\GraphQL\Builder\FieldBuilder;
use MonkeysLegion\GraphQL\Builder\InputTypeBuilder;
use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Builder\TypeBuilder;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Error\ErrorHandler;
use MonkeysLegion\GraphQL\Executor\QueryExecutor;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use MonkeysLegion\GraphQL\Scanner\AttributeScanner;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Integration test: executes real GraphQL queries against a schema
 * built from fixtures and validates the results.
 */
final class QueryExecutionTest extends TestCase
{
    private Schema $schema;
    private QueryExecutor $executor;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = $this->createContainer();

        $argumentBuilder  = new ArgumentBuilder();
        $enumBuilder      = new EnumBuilder();
        $fieldBuilder     = new FieldBuilder($argumentBuilder, $this->container);
        $typeBuilder      = new TypeBuilder($fieldBuilder);
        $inputTypeBuilder = new InputTypeBuilder($argumentBuilder);
        $scanner          = new AttributeScanner();

        $schemaBuilder = new SchemaBuilder(
            $scanner,
            $typeBuilder,
            $enumBuilder,
            $inputTypeBuilder,
            $argumentBuilder,
            $this->container,
        );

        $this->schema   = $schemaBuilder->build([__DIR__ . '/../Fixtures']);
        $this->executor = new QueryExecutor(new ErrorHandler(false));
    }

    public function testSimpleQueryExecution(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            '{ users { id name email } }',
            $this->createContext(),
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']['users']);
        $this->assertCount(2, $result['data']['users']);

        $firstUser = $result['data']['users'][0];
        $this->assertSame(1, $firstUser['id']);
        $this->assertSame('Alice', $firstUser['name']);
        $this->assertSame('alice@test.com', $firstUser['email']);
    }

    public function testQueryWithArguments(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            '{ post(id: 1) { id title body } }',
            $this->createContext(),
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertNotNull($result['data']['post']);
        $this->assertSame(1, $result['data']['post']['id']);
        $this->assertSame('Hello World', $result['data']['post']['title']);
        $this->assertSame('First post!', $result['data']['post']['body']);
    }

    public function testQueryWithVariables(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            'query GetPost($id: Int!) { post(id: $id) { id title } }',
            $this->createContext(),
            ['id' => 2],
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertSame(2, $result['data']['post']['id']);
        $this->assertSame('GraphQL Rocks', $result['data']['post']['title']);
    }

    public function testQueryReturnsNullForMissingEntity(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            '{ post(id: 999) { id title } }',
            $this->createContext(),
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertNull($result['data']['post']);
    }

    public function testInvalidQueryReturnsErrors(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            '{ nonExistentField }',
            $this->createContext(),
        );

        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    public function testSyntaxErrorReturnsErrors(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            '{ users { id name }',
            $this->createContext(),
        );

        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    public function testMultipleRootFieldsInSingleQuery(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            '{ users { id name } post(id: 1) { id title } }',
            $this->createContext(),
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']['users']);
        $this->assertNotNull($result['data']['post']);
        $this->assertSame('Hello World', $result['data']['post']['title']);
    }

    public function testOperationNameSelection(): void
    {
        $query = <<<'GRAPHQL'
        query GetUsers { users { id name } }
        query GetPost { post(id: 2) { id title } }
        GRAPHQL;

        $result = $this->executor->execute(
            $this->schema,
            $query,
            $this->createContext(),
            null,
            'GetPost',
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('post', $result['data']);
        $this->assertSame(2, $result['data']['post']['id']);
    }

    public function testExecuteRawReturnsExecutionResult(): void
    {
        $executionResult = $this->executor->executeRaw(
            $this->schema,
            '{ users { id } }',
            $this->createContext(),
        );

        $this->assertInstanceOf(\GraphQL\Executor\ExecutionResult::class, $executionResult);
        $this->assertEmpty($executionResult->errors);
        $this->assertNotNull($executionResult->data);
    }

    /**
     * Create a GraphQL context for testing.
     */
    private function createContext(): GraphQLContext
    {
        return new GraphQLContext(
            request: new ServerRequest('POST', '/graphql'),
            user: null,
            container: $this->container,
            loaders: new DataLoaderRegistry($this->container),
        );
    }

    /**
     * Create a minimal PSR-11 container.
     */
    private function createContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            /** @var array<string, object> */
            private array $instances = [];

            public function get(string $id): mixed
            {
                if (!isset($this->instances[$id])) {
                    if (class_exists($id)) {
                        $this->instances[$id] = new $id();
                    } else {
                        throw new \RuntimeException("Service not found: {$id}");
                    }
                }
                return $this->instances[$id];
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };
    }
}
