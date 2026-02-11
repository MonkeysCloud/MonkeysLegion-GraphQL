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
 * Integration test: executes GraphQL mutations against a schema
 * built from fixtures and validates side-effect results.
 */
final class MutationExecutionTest extends TestCase
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

    public function testCreatePostMutation(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            'mutation { createPost(title: "Test Post", body: "Test body") { id title body } }',
            $this->createContext(),
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertNotNull($result['data']['createPost']);
        $this->assertSame(1, $result['data']['createPost']['id']);
        $this->assertSame('Test Post', $result['data']['createPost']['title']);
        $this->assertSame('Test body', $result['data']['createPost']['body']);
    }

    public function testCreatePostMutationWithVariables(): void
    {
        $query = <<<'GRAPHQL'
        mutation CreatePost($title: String!, $body: String!) {
            createPost(title: $title, body: $body) {
                id
                title
                body
                authorId
            }
        }
        GRAPHQL;

        $result = $this->executor->execute(
            $this->schema,
            $query,
            $this->createContext(),
            ['title' => 'Variable Post', 'body' => 'Variable body'],
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertNotNull($result['data']['createPost']);
        $this->assertSame('Variable Post', $result['data']['createPost']['title']);
        $this->assertSame('Variable body', $result['data']['createPost']['body']);
        $this->assertSame(1, $result['data']['createPost']['authorId']);
    }

    public function testMutationMissingRequiredArgReturnsError(): void
    {
        $result = $this->executor->execute(
            $this->schema,
            'mutation { createPost(title: "No Body") { id } }',
            $this->createContext(),
        );

        // Should error because 'body' is required String!
        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    public function testMutationAndQueryInSameDocument(): void
    {
        $query = <<<'GRAPHQL'
        mutation CreateAndQuery {
            createPost(title: "New", body: "Content") {
                id
                title
            }
        }
        GRAPHQL;

        $result = $this->executor->execute(
            $this->schema,
            $query,
            $this->createContext(),
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertNotNull($result['data']['createPost']);
        $this->assertSame('New', $result['data']['createPost']['title']);
    }

    public function testMutationReturnsObjectTypeFields(): void
    {
        // Verify that the mutation return type maps correctly to Post fields
        $result = $this->executor->execute(
            $this->schema,
            'mutation { createPost(title: "Typed", body: "Check") { id title body authorId } }',
            $this->createContext(),
        );

        $this->assertArrayHasKey('data', $result);
        $post = $result['data']['createPost'];
        $this->assertIsInt($post['id']);
        $this->assertIsString($post['title']);
        $this->assertIsString($post['body']);
        $this->assertIsInt($post['authorId']);
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
