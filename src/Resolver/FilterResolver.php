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
     * Supported operator suffixes and their repository-level operators.
     *
     * @var array<string, string>
     */
    private const OPERATOR_MAP = [
        '_between' => 'BETWEEN',
        '_not_in'  => 'NOT IN',
        '_not'     => '!=',
        '_like'    => 'LIKE',
        '_gte'     => '>=',
        '_lte'     => '<=',
        '_gt'      => '>',
        '_lt'      => '<',
        '_in'      => 'IN',
    ];

    /**
     * Parse where and orderBy args into repository criteria.
     *
     * Criteria entries are returned as:
     *   - ['field' => value]                   for equality
     *   - ['field' => ['operator', value]]     for non-equality operators
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
            foreach ($args['where'] as $key => $value) {
                [$field, $operator] = self::parseOperator($key);

                if ($operator === '=') {
                    $criteria[$field] = $value;
                } else {
                    $criteria[$field] = [$operator, $value];
                }
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

    /**
     * Parse a filter key into its field name and operator.
     *
     * E.g. 'price_gt' => ['price', '>']
     *      'status'   => ['status', '=']
     *      'id_in'    => ['id', 'IN']
     *
     * @param string $key The filter key (e.g. 'price_gt', 'status', 'tags_not_in')
     *
     * @return array{0: string, 1: string} [field, operator]
     */
    private static function parseOperator(string $key): array
    {
        // Check longest suffixes first to avoid '_not' matching '_not_in'
        foreach (self::OPERATOR_MAP as $suffix => $operator) {
            if (str_ends_with($key, $suffix)) {
                $field = substr($key, 0, -strlen($suffix));
                if ($field !== '') {
                    return [$field, $operator];
                }
            }
        }

        return [$key, '='];
    }
}
