<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Types;

use MonkeysLegion\GraphQL\Attribute\Type;
use MonkeysLegion\GraphQL\Attribute\Field;

#[Type(name: 'Post', description: 'A blog post')]
final class PostType
{
    #[Field]
    public function id(object $root): int
    {
        return $root->id ?? 0;
    }

    #[Field]
    public function title(object $root): string
    {
        return $root->title ?? '';
    }

    #[Field]
    public function body(object $root): string
    {
        return $root->body ?? '';
    }

    #[Field(name: 'authorId')]
    public function authorId(object $root): int
    {
        return $root->authorId ?? 0;
    }
}
