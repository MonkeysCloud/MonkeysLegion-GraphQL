<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Exposes an Entity as a GraphQL resource, automatically generating
 * queries (find, list) and mutations (create, update, delete).
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class GraphQLResource
{
    /**
     * @param list<string> $operations The CRUD operations to generate. Defaults to all.
     *                                 Valid values: 'find', 'list', 'create', 'update', 'delete'.
     * @param string|null  $queryName  Custom singular name for queries (defaults to entity name).
     * @param class-string|null $repositoryClass The repository class to use for database operations.
     * @param bool $paginateList Whether the list query should be paginated (Relay connection)
     */
    public function __construct(
        public readonly array $operations = ['find', 'list', 'create', 'update', 'delete'],
        public readonly ?string $queryName = null,
        public readonly ?string $repositoryClass = null,
        public readonly bool $paginateList = true,
    ) {}

    public function hasOperation(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }
}
