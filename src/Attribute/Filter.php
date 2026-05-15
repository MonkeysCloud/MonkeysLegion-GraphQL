<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Exposes an entity property as a filterable field in GraphQL queries.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Filter
{
    /**
     * @param list<string> $operators Allowed operators ('eq', 'neq', 'in', 'gt', 'lt', 'like')
     */
    public function __construct(
        public readonly array $operators = ['eq'],
    ) {}
}
