<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Queries;

use MonkeysLegion\GraphQL\Attribute\Query;
use MonkeysLegion\GraphQL\Attribute\Arg;
use MonkeysLegion\GraphQL\Context\GraphQLContext;

#[Query(name: 'users', description: 'Get all users', type: '[User!]!')]
final class GetUsersQuery
{
    /** @return array<object> */
    public function __invoke(mixed $root, GraphQLContext $context): array
    {
        return [
            (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@test.com'],
            (object) ['id' => 2, 'name' => 'Bob', 'email' => 'bob@test.com'],
        ];
    }
}
