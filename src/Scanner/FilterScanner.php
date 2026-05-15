<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Scanner;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InputObjectType;
use MonkeysLegion\GraphQL\Attribute\Filter;
use MonkeysLegion\GraphQL\Attribute\Sort;
use MonkeysLegion\GraphQL\Attribute\Search;
use ReflectionClass;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Scans entities for #[Filter], #[Sort], and #[Search] attributes
 * and generates corresponding GraphQL Input Types for list queries.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class FilterScanner
{
    /** @var array<string, array{where: ?InputObjectType, orderBy: ?\GraphQL\Type\Definition\ListOfType, search: bool}> */
    public private(set) array $mappings = [];

    /**
     * Map an entity class to Filter/Sort/Search configurations.
     *
     * @param class-string $entityClass
     *
     * @return array{where: ?InputObjectType, orderBy: ?\GraphQL\Type\Definition\ListOfType, search: bool}
     */
    public function map(string $entityClass): array
    {
        if (isset($this->mappings[$entityClass])) {
            return $this->mappings[$entityClass];
        }

        $reflection = new ReflectionClass($entityClass);
        $shortName = $reflection->getShortName();

        $whereFields = [];
        $sortableFields = [];
        $searchable = false;

        foreach ($reflection->getProperties() as $property) {
            $propName = $property->getName();

            // Filter
            $filterAttrs = $property->getAttributes(Filter::class);
            if ($filterAttrs !== []) {
                /** @var Filter $attr */
                $attr = $filterAttrs[0]->newInstance();
                
                // Simplified: assuming scalar types. A real implementation would parse PHP type.
                // We just accept strings for equality filters in this demo version.
                foreach ($attr->operators as $op) {
                    $fieldName = $op === 'eq' ? $propName : "{$propName}_{$op}";
                    $whereFields[$fieldName] = [
                        'type' => Type::string(),
                    ];
                }
            }

            // Sort
            if ($property->getAttributes(Sort::class) !== []) {
                $sortableFields[$propName] = [
                    'value' => $propName,
                ];
            }

            // Search
            if ($property->getAttributes(Search::class) !== []) {
                $searchable = true;
            }
        }

        $whereType = null;
        if ($whereFields !== []) {
            $whereType = new InputObjectType([
                'name' => "{$shortName}WhereInput",
                'fields' => $whereFields,
            ]);
        }

        $orderByType = null;
        if ($sortableFields !== []) {
            // In a complete implementation, this would be a List of InputObjects with field enum and direction enum.
            // Simplified: list of strings (e.g. ['+created_at', '-name'])
            $orderByType = Type::listOf(Type::string());
        }

        $mapping = [
            'where' => $whereType,
            'orderBy' => $orderByType,
            'search' => $searchable,
        ];

        $this->mappings[$entityClass] = $mapping;
        return $mapping;
    }
}
