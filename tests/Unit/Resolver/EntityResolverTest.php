<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Resolver;

use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use MonkeysLegion\GraphQL\Resolver\EntityResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

final class EntityResolverTest extends TestCase
{
    private function makeContext(ContainerInterface $container): GraphQLContext
    {
        return new GraphQLContext(
            $this->createMock(ServerRequestInterface::class),
            null,
            $container,
            new DataLoaderRegistry(),
        );
    }

    public function testFindByIdReturnsEntity(): void
    {
        $entity = (object) ['id' => 1, 'name' => 'Alice'];

        $repo = new class($entity) {
            public function __construct(private object $entity) {}
            public function find(int $id): ?object { return $id === 1 ? $this->entity : null; }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($repo);

        $resolver = EntityResolver::findById('App\\Entity\\User', 'App\\Repo\\UserRepo');
        $result = $resolver(null, ['id' => 1], $this->makeContext($container));

        $this->assertSame($entity, $result);
    }

    public function testFindByIdReturnsNullWhenNoRepo(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $resolver = EntityResolver::findById('App\\Entity\\User');
        $result = $resolver(null, ['id' => 1], $this->makeContext($container));

        $this->assertNull($result);
    }

    public function testFindByIdReturnsNullWhenNoId(): void
    {
        $repo = new class { public function find(int $id): ?object { return null; } };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($repo);

        $resolver = EntityResolver::findById('App\\Entity\\User', 'App\\Repo\\UserRepo');
        $result = $resolver(null, [], $this->makeContext($container));

        $this->assertNull($result);
    }

    public function testFindAllReturnsEntities(): void
    {
        $entities = [(object) ['id' => 1], (object) ['id' => 2]];
        $repo = new class($entities) {
            public function __construct(private array $data) {}
            public function findAll(): array { return $this->data; }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($repo);

        $resolver = EntityResolver::findAll('App\\Entity\\User', 'App\\Repo\\UserRepo');
        $result = $resolver(null, [], $this->makeContext($container));

        $this->assertCount(2, $result);
    }

    public function testFindAllReturnsEmptyWhenNoRepo(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $resolver = EntityResolver::findAll('App\\Entity\\User');
        $result = $resolver(null, [], $this->makeContext($container));

        $this->assertSame([], $result);
    }

    public function testConnectionReturnsRelayStructure(): void
    {
        $items = [(object) ['id' => 1], (object) ['id' => 2], (object) ['id' => 3]];
        $repo = new class($items) {
            public function __construct(private array $data) {}
            public function findAll(): array { return $this->data; }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($repo);

        $resolver = EntityResolver::connection('App\\Entity\\User', 'App\\Repo\\UserRepo');
        $result = $resolver(null, ['first' => 2], $this->makeContext($container));

        $this->assertCount(2, $result['edges']);
        $this->assertTrue($result['pageInfo']['hasNextPage']);
        $this->assertFalse($result['pageInfo']['hasPreviousPage']);
        $this->assertNotNull($result['pageInfo']['startCursor']);
        $this->assertNotNull($result['pageInfo']['endCursor']);
    }

    public function testConnectionReturnsEmptyWhenNoRepo(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $resolver = EntityResolver::connection('App\\Entity\\User');
        $result = $resolver(null, ['first' => 5], $this->makeContext($container));

        $this->assertSame([], $result['edges']);
        $this->assertFalse($result['pageInfo']['hasNextPage']);
        $this->assertSame(0, $result['totalCount']);
    }

    public function testConnectionWithAfterCursor(): void
    {
        $items = [(object) ['id' => 1], (object) ['id' => 2], (object) ['id' => 3], (object) ['id' => 4]];
        $repo = new class($items) {
            public function __construct(private array $data) {}
            public function findAll(): array { return $this->data; }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($repo);

        $resolver = EntityResolver::connection('App\\Entity\\User', 'App\\Repo\\UserRepo');
        $after = base64_encode('1'); // cursor for offset 1, so start at index 2
        $result = $resolver(null, ['first' => 10, 'after' => $after], $this->makeContext($container));

        $this->assertTrue($result['pageInfo']['hasPreviousPage']);
    }
}
