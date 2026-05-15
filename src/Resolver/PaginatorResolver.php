<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Resolver;

use MonkeysLegion\Query\Repository\EntityRepository;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Resolves query arguments to execute cursor pagination on a repository
 * and maps the result to a Relay-compliant Connection array.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class PaginatorResolver
{
    /**
     * Execute cursor pagination and return a Relay Connection.
     *
     * @param EntityRepository $repository
     * @param array<string, mixed> $args
     *
     * @return array{
     *     edges: list<array{cursor: string, node: object}>,
     *     pageInfo: array{hasNextPage: bool, hasPreviousPage: bool, startCursor: ?string, endCursor: ?string},
     *     totalCount: int
     * }
     */
    public static function resolve(EntityRepository $repository, array $args): array
    {
        $first = $args['first'] ?? 15;
        $after = $args['after'] ?? null;
        
        // Decode cursor if present
        $cursor = $after ? self::decodeCursor($after) : null;

        [$criteria, $orderBy] = FilterResolver::extractCriteria($args);

        $result = $repository->cursorPaginate(
            cursor: $cursor,
            perPage: $first,
            column: 'id', // Simplification: in a real scenario we could support sorting columns
            criteria: $criteria,
            orderBy: $orderBy,
        );

        $edges = [];
        foreach ($result['data'] as $entity) {
            $entityId = (string) $entity->id; // Assumes id is accessible
            $edges[] = [
                'cursor' => self::encodeCursor($entityId),
                'node' => $entity,
            ];
        }

        $startCursor = $edges !== [] ? $edges[0]['cursor'] : null;
        $endCursor = $edges !== [] ? end($edges)['cursor'] : null;

        return [
            'edges' => $edges,
            'pageInfo' => [
                'hasNextPage' => $result['hasMore'],
                'hasPreviousPage' => $after !== null,
                'startCursor' => $startCursor,
                'endCursor' => $endCursor,
            ],
            // totalCount usually requires a separate count() query,
            // which cursor pagination avoids. If needed, we can call $repository->count()
            // but for performance we might return 0 or do it lazily.
            'totalCount' => 0,
        ];
    }

    private static function encodeCursor(string $id): string
    {
        return base64_encode('cursor:' . $id);
    }

    private static function decodeCursor(string $encoded): ?string
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || !str_starts_with($decoded, 'cursor:')) {
            return null;
        }
        return substr($decoded, 7);
    }
}
