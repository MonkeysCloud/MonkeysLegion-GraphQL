<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Marks a class as a GraphQL Object Type.
 *
 * Methods annotated with #[Field] become fields on this type.
 * The class constructor may receive DI services.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Type
{
    /**
     * @param string|null $name        GraphQL type name (defaults to class short name minus "Type" suffix)
     * @param string|null $description Human-readable description for introspection
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}