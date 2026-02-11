<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Marks a class as a GraphQL Subscription field.
 *
 * The class must have subscribe() and resolve() methods.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Subscription
{
    /**
     * @param string      $name        Subscription field name
     * @param string|null $description Human-readable description
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
    ) {}
}