<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Builder\AutoCrudBuilder;
use MonkeysLegion\GraphQL\Tests\Fixtures\Entity\User;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InputObjectType;
use Psr\Container\ContainerInterface;

class AutoCrudBuilderTest extends TestCase
{
    public function testBuildQueriesAndMutations(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $builder = new AutoCrudBuilder($container);

        $resources = [User::class];

        $typeResolver = static function(string $class) {
            return new ObjectType(['name' => 'User', 'fields' => ['id' => Type::id()]]);
        };

        $inputResolver = static function(string $class, bool $isUpdate) {
            return new InputObjectType(['name' => $isUpdate ? 'UpdateUserInput' : 'CreateUserInput', 'fields' => ['name' => Type::string()]]);
        };

        $queries = $builder->buildQueries($resources, $typeResolver);
        $mutations = $builder->buildMutations($resources, $typeResolver, $inputResolver);

        $this->assertArrayHasKey('user', $queries);
        $this->assertArrayHasKey('users', $queries);
        
        $this->assertArrayHasKey('createUser', $mutations);
        $this->assertArrayHasKey('updateUser', $mutations);
        $this->assertArrayHasKey('deleteUser', $mutations);
    }
}
