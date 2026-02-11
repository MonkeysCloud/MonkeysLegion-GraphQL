<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Queries;

use MonkeysLegion\GraphQL\Attribute\Query;
use MonkeysLegion\GraphQL\Attribute\Arg;
use MonkeysLegion\GraphQL\Context\GraphQLContext;

#[Query(name: 'post', description: 'Get a post by ID', type: 'Post')]
final class GetPostQuery
{
    public function __invoke(
        mixed $root,
        #[Arg(description: 'Post ID')] int $id,
        GraphQLContext $context,
    ): ?object {
        // Simulate a post lookup
        $posts = [
            1 => (object) ['id' => 1, 'title' => 'Hello World', 'body' => 'First post!', 'authorId' => 1],
            2 => (object) ['id' => 2, 'title' => 'GraphQL Rocks', 'body' => 'Second post', 'authorId' => 2],
        ];

        return $posts[$id] ?? null;
    }
}
