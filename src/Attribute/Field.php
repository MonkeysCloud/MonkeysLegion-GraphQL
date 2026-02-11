<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Marks a method or property as a GraphQL field on a Type.
 *
 * When applied to a method, the method's first parameter receives the parent/root object.
 * Return type is inferred from PHP type hints unless overridden via $type.
 * Additional parameters are resolved: typed as GraphQLContext → injected,
 * typed as a service → DI-resolved, typed as scalar with #[Arg] → becomes an argument.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class Field
{
    /**
     * @param string|null $name              Field name (defaults to method/property name)
     * @param string|null $type              GraphQL type string override, e.g. '[Post!]!'
     * @param string|null $description       Human-readable description
     * @param string|null $deprecationReason Marks the field as deprecated with the given reason
     * @param int|null    $complexity        Cost for complexity analysis (default 1)
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?string $description = null,
        public readonly ?string $deprecationReason = null,
        public readonly ?int $complexity = null,
    ) {}
}
