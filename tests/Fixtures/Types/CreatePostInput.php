<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Types;

use MonkeysLegion\GraphQL\Attribute\InputType;

#[InputType(name: 'CreatePostInput', description: 'Input for creating a post')]
final class CreatePostInput
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $status = 'DRAFT',
    ) {}
}
