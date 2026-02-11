<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Marks a PHP interface or abstract class as a GraphQL Interface Type.
 *
 * Methods annotated with #[Field] define the interface contract.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class InterfaceType
{
    /**
     * @param string|null $name        Interface type name (defaults to class short name)
     * @param string|null $description Human-readable description
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}
