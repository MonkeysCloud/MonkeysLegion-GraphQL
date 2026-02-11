<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Types;

use MonkeysLegion\GraphQL\Attribute\Type;
use MonkeysLegion\GraphQL\Attribute\Field;

#[Type(name: 'User', description: 'A user entity')]
final class UserType
{
    #[Field(description: 'Unique identifier')]
    public function id(object $root): int
    {
        return $root->id ?? 0;
    }

    #[Field(description: 'User display name')]
    public function name(object $root): string
    {
        return $root->name ?? '';
    }

    #[Field(description: 'Email address')]
    public function email(object $root): string
    {
        return $root->email ?? '';
    }

    #[Field(name: 'createdAt', description: 'Account creation date')]
    public function createdAt(object $root): ?string
    {
        return $root->createdAt ?? null;
    }
}
