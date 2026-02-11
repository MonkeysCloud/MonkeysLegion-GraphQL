<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Marks a backed PHP enum as a GraphQL Enum Type.
 *
 * Enum cases become GraphQL enum values.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Enum
{
    /**
     * @param string|null $name        Enum type name (defaults to enum short name)
     * @param string|null $description Human-readable description
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}
