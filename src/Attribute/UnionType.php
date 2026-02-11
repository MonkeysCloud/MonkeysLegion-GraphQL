<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Marks a class as a GraphQL Union Type.
 *
 * The class must have a resolveType(mixed $value): string method that returns
 * the FQCN of the concrete type for a given value.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class UnionType
{
    /**
     * @param string      $name        Union type name
     * @param array       $types       FQCNs of member type classes
     * @param string|null $description Human-readable description
     */
    public function __construct(
        public readonly string $name,
        public readonly array $types = [],
        public readonly ?string $description = null,
    ) {}
}
