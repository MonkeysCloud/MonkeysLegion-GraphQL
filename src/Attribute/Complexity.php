<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Annotates a field or resolver method with an explicit complexity cost.
 * Used by the ComplexityAnalyzer to calculate per-field query costs.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class Complexity
{
    /**
     * @param int $cost        Static cost for this field
     * @param int $multiplier  Multiplier applied when this field returns a list
     */
    public function __construct(
        public readonly int $cost = 1,
        public readonly int $multiplier = 1,
    ) {}
}
