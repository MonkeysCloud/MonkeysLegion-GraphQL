<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Queries;

use MonkeysLegion\GraphQL\Attribute\Mutation;
use MonkeysLegion\GraphQL\Attribute\Arg;
use MonkeysLegion\GraphQL\Context\GraphQLContext;

#[Mutation(name: 'createPost', description: 'Create a new post', type: 'Post!')]
final class CreatePostMutation
{
    public function __invoke(
        mixed $root,
        #[Arg(description: 'Post title')] string $title,
        #[Arg(description: 'Post body')] string $body,
        GraphQLContext $context,
    ): object {
        return (object) [
            'id'       => 1,
            'title'    => $title,
            'body'     => $body,
            'authorId' => 1,
        ];
    }
}
