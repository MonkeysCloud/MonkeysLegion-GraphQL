<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Types;

use MonkeysLegion\GraphQL\Attribute\Enum;

#[Enum(name: 'PostStatus', description: 'Status of a blog post')]
enum PostStatusEnum: string
{
    case Draft     = 'DRAFT';
    case Published = 'PUBLISHED';
    case Archived  = 'ARCHIVED';
}
