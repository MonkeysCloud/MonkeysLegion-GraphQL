<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Marks a class as a GraphQL Input Object Type.
 *
 * Constructor parameters become input fields. Use readonly promoted properties.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class InputType
{
    /**
     * @param string|null $name        Input type name (defaults to class short name)
     * @param string|null $description Human-readable description
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}
