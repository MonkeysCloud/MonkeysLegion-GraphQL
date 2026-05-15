<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Integration\Loader;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Loader\EntityDataLoader;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use Psr\Container\ContainerInterface;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use MonkeysLegion\Query\Repository\EntityRepository;

require_once __DIR__ . '/../../Fixtures/EntityRepository.php';

class DataLoaderExecutionTest extends TestCase
{
    public function testDataLoaderBatchesExecutionInSchema(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $loader = new EntityDataLoader();
        
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(function($id) use ($loader, $repository) {
            if ($id === EntityDataLoader::class) {
                return $loader;
            }
            return $repository;
        });

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        // We will just pass null for loaders if allowed, or mock it.
        // Based on signature: ServerRequestInterface $request, object|null $user, ContainerInterface $container, DataLoaderRegistry $loaders
        // Wait, if it expects DataLoaderRegistry, let's create a stub object since we might not have it in the FQCN here, or use createMock if we know the class.
        // Assuming \Overblog\DataLoader\DataLoaderRegistry or similar, but the user message says DataLoaderRegistry.
        // We can just use a dummy object for the 4th argument, or null if it's not strictly typed without null.
        // Looking at the user's error message: `DataLoaderRegistry $loaders`.
        
        $loaders = new \MonkeysLegion\GraphQL\Loader\DataLoaderRegistry();
        $context = new GraphQLContext($request, null, $container, $loaders);

        // Expect findByIds to be called exactly once with [1, 2]
        $repository->expects($this->once())
            ->method('findByIds')
            ->with([1, 2])
            ->willReturn([
                (object) ['id' => 1, 'name' => 'John'],
                (object) ['id' => 2, 'name' => 'Jane'],
            ]);

        $userType = new ObjectType([
            'name' => 'User',
            'fields' => [
                'id' => Type::id(),
                'name' => Type::string(),
            ]
        ]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'users' => [
                    'type' => Type::listOf($userType),
                    'resolve' => function () {
                        // Return stubs that need hydration
                        return [
                            (object) ['user_id' => 1],
                            (object) ['user_id' => 2],
                        ];
                    }
                ],
            ]
        ]);

        // Wrap the user fields to use data loader
        $userType->config['fields'] = function () use ($repository, $loader) {
            return [
                'id' => [
                    'type' => Type::id(),
                    'resolve' => fn($root) => $root->id ?? $root->user_id
                ],
                'name' => [
                    'type' => Type::string(),
                    'resolve' => function ($root) use ($repository, $loader) {
                        return $loader->queueById($repository, $root->user_id)->then(fn($user) => $user->name ?? null);
                    }
                ]
            ];
        };

        $schema = new Schema(['query' => $queryType]);

        $query = '{ users { id name } }';
        $result = GraphQL::executeQuery($schema, $query, null, $context);
        
        $data = $result->toArray();

        $this->assertArrayNotHasKey('errors', $data);
        $this->assertEquals('John', $data['data']['users'][0]['name']);
        $this->assertEquals('Jane', $data['data']['users'][1]['name']);
    }
}
