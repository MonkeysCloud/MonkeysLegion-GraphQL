<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Resolver;

use MonkeysLegion\Query\Repository\EntityRepository;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Resolves filter and sort arguments and executes the query.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class FilterResolver
{
    /**
     * Parse where and orderBy args into repository criteria.
     *
     * @param array<string, mixed> $args
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function extractCriteria(array $args): array
    {
        $criteria = [];
        $orderBy = [];

        if (isset($args['where']) && is_array($args['where'])) {
            // Very simplified: this assumes simple equality mappings.
            // In a full Lighthouse parity, we would map 'id_gt' to ['id', '>', value] 
            // which requires exposing the QueryBuilder. EntityRepository has a `query()` method.
            foreach ($args['where'] as $key => $value) {
                // If the key has no suffix, assume equality.
                // For demonstration, we just pass to criteria (which does equality).
                $criteria[$key] = $value;
            }
        }

        if (isset($args['orderBy']) && is_array($args['orderBy'])) {
            foreach ($args['orderBy'] as $sort) {
                if (str_starts_with($sort, '-')) {
                    $orderBy[substr($sort, 1)] = 'desc';
                } elseif (str_starts_with($sort, '+')) {
                    $orderBy[substr($sort, 1)] = 'asc';
                } else {
                    $orderBy[$sort] = 'asc';
                }
            }
        }

        return [$criteria, $orderBy];
    }

    /**
     * Resolve all entities matching the filters (no pagination).
     *
     * @param EntityRepository $repository
     * @param array<string, mixed> $args
     *
     * @return list<object>
     */
    public static function resolveAll(EntityRepository $repository, array $args): array
    {
        [$criteria, $orderBy] = self::extractCriteria($args);

        return $repository->findBy($criteria, $orderBy);
    }
}
