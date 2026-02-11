<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Integration;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\SchemaPrinter;
use MonkeysLegion\GraphQL\Builder\ArgumentBuilder;
use MonkeysLegion\GraphQL\Builder\EnumBuilder;
use MonkeysLegion\GraphQL\Builder\FieldBuilder;
use MonkeysLegion\GraphQL\Builder\InputTypeBuilder;
use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Builder\TypeBuilder;
use MonkeysLegion\GraphQL\Scanner\AttributeScanner;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Integration test: builds a full schema from the test fixtures directory
 * and validates the resulting type system.
 */
final class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $builder;
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

        $this->builder = new SchemaBuilder(
            $scanner,
            $typeBuilder,
            $enumBuilder,
            $inputTypeBuilder,
            $argumentBuilder,
            $this->container,
        );
    }

    public function testBuildSchemaFromFixtures(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);

        // Schema should be valid
        $schema->assertValid();
        $this->assertTrue(true, 'Schema is valid');
    }

    public function testQueryTypeHasExpectedFields(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);

        $queryType = $schema->getQueryType();
        $this->assertInstanceOf(ObjectType::class, $queryType);

        $queryFields = $queryType->getFieldNames();
        $this->assertContains('users', $queryFields, 'Query type should have "users" field');
        $this->assertContains('post', $queryFields, 'Query type should have "post" field');
    }

    public function testMutationTypeHasExpectedFields(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);

        $mutationType = $schema->getMutationType();
        $this->assertInstanceOf(ObjectType::class, $mutationType);

        $mutationFields = $mutationType->getFieldNames();
        $this->assertContains('createPost', $mutationFields, 'Mutation type should have "createPost" field');
    }

    public function testObjectTypesAreRegistered(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);

        $typeMap = $this->builder->getTypeMap();

        $this->assertArrayHasKey('Post', $typeMap, 'Type map should contain "Post"');
        $this->assertArrayHasKey('User', $typeMap, 'Type map should contain "User"');
    }

    public function testPostTypeHasCorrectFields(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);
        $typeMap = $this->builder->getTypeMap();

        $this->assertArrayHasKey('Post', $typeMap);
        $postType = $typeMap['Post'];
        $this->assertInstanceOf(ObjectType::class, $postType);

        $fieldNames = $postType->getFieldNames();
        $this->assertContains('id', $fieldNames);
        $this->assertContains('title', $fieldNames);
        $this->assertContains('body', $fieldNames);
        $this->assertContains('authorId', $fieldNames);
    }

    public function testUserTypeHasCorrectFields(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);
        $typeMap = $this->builder->getTypeMap();

        $this->assertArrayHasKey('User', $typeMap);
        $userType = $typeMap['User'];
        $this->assertInstanceOf(ObjectType::class, $userType);

        $fieldNames = $userType->getFieldNames();
        $this->assertContains('id', $fieldNames);
        $this->assertContains('name', $fieldNames);
        $this->assertContains('email', $fieldNames);
        $this->assertContains('createdAt', $fieldNames);
    }

    public function testEnumTypeIsRegistered(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);
        $typeMap = $this->builder->getTypeMap();

        $this->assertArrayHasKey('PostStatus', $typeMap);
    }

    public function testInputTypeIsRegistered(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);
        $typeMap = $this->builder->getTypeMap();

        $this->assertArrayHasKey('CreatePostInput', $typeMap);
    }

    public function testQueryFieldHasArguments(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);

        $queryType = $schema->getQueryType();
        $this->assertNotNull($queryType);

        $postField = $queryType->getField('post');
        $this->assertNotNull($postField);

        $argNames = array_map(
            static fn($arg) => $arg->name,
            $postField->args,
        );
        $this->assertContains('id', $argNames, 'post query should have "id" argument');
    }

    public function testMutationFieldHasArguments(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);

        $mutationType = $schema->getMutationType();
        $this->assertNotNull($mutationType);

        $createPostField = $mutationType->getField('createPost');
        $this->assertNotNull($createPostField);

        $argNames = array_map(
            static fn($arg) => $arg->name,
            $createPostField->args,
        );
        $this->assertContains('title', $argNames, 'createPost mutation should have "title" argument');
        $this->assertContains('body', $argNames, 'createPost mutation should have "body" argument');
    }

    public function testSchemaCanBePrintedAsSdl(): void
    {
        $schema = $this->builder->build([__DIR__ . '/../Fixtures']);

        $sdl = SchemaPrinter::doPrint($schema);
        $this->assertNotEmpty($sdl);
        $this->assertStringContainsString('type Post', $sdl);
        $this->assertStringContainsString('type User', $sdl);
        $this->assertStringContainsString('type Query', $sdl);
        $this->assertStringContainsString('type Mutation', $sdl);
        $this->assertStringContainsString('enum PostStatus', $sdl);
    }

    /**
     * Create a minimal PSR-11 container that auto-instantiates fixture classes.
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
