<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Wraps a GraphQL field to return a paginated collection.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class Paginate
{
    /**
     * @param string $type The pagination type ('cursor' or 'offset')
     * @param int $defaultCount Default items per page
     * @param int $maxCount Maximum items per page
     */
    public function __construct(
        public readonly string $type = 'cursor',
        public readonly int $defaultCount = 15,
        public readonly int $maxCount = 100,
    ) {}
}
