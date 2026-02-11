<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Resolver;

use MonkeysLegion\GraphQL\Context\GraphQLContext;

/**
 * Generic entity resolver for auto-mapped entities.
 *
 * Provides default CRUD-style resolvers for entities discovered by
 * EntityTypeMapper. Uses the DI container to access repositories.
 */
final class EntityResolver
{
    /**
     * Create a resolver function for fetching a single entity by ID.
     *
     * @param class-string $entityClass  The entity class
     * @param string       $repositoryClass The repository class (or entity class for container lookup)
     *
     * @return callable
     */
    public static function findById(string $entityClass, ?string $repositoryClass = null): callable
    {
        return static function (
            mixed $root,
            array $args,
            GraphQLContext $context,
        ) use ($entityClass, $repositoryClass): mixed {
            $repoClass = $repositoryClass ?? $entityClass . 'Repository';
            $container = $context->container;

            if (!$container->has($repoClass)) {
                return null;
            }

            $repository = $container->get($repoClass);
            $id = $args['id'] ?? null;

            if ($id === null) {
                return null;
            }

            if (method_exists($repository, 'find')) {
                return $repository->find($id);
            }

            if (method_exists($repository, 'findById')) {
                return $repository->findById($id);
            }

            return null;
        };
    }

    /**
     * Create a resolver function for fetching all entities.
     *
     * @param class-string $entityClass     The entity class
     * @param string|null  $repositoryClass The repository class
     *
     * @return callable
     */
    public static function findAll(string $entityClass, ?string $repositoryClass = null): callable
    {
        return static function (
            mixed $root,
            array $args,
            GraphQLContext $context,
        ) use ($entityClass, $repositoryClass): array {
            $repoClass = $repositoryClass ?? $entityClass . 'Repository';
            $container = $context->container;

            if (!$container->has($repoClass)) {
                return [];
            }

            $repository = $container->get($repoClass);

            if (method_exists($repository, 'findAll')) {
                return $repository->findAll();
            }

            if (method_exists($repository, 'all')) {
                return $repository->all();
            }

            return [];
        };
    }

    /**
     * Create a resolver for paginated entity fetching (Relay-style).
     *
     * @param class-string $entityClass     The entity class
     * @param string|null  $repositoryClass The repository class
     *
     * @return callable
     */
    public static function connection(string $entityClass, ?string $repositoryClass = null): callable
    {
        return static function (
            mixed $root,
            array $args,
            GraphQLContext $context,
        ) use ($entityClass, $repositoryClass): array {
            $repoClass = $repositoryClass ?? $entityClass . 'Repository';
            $container = $context->container;

            $first = $args['first'] ?? 10;
            $after = $args['after'] ?? null;

            if (!$container->has($repoClass)) {
                return [
                    'edges'    => [],
                    'pageInfo' => [
                        'hasNextPage'     => false,
                        'hasPreviousPage' => false,
                        'startCursor'     => null,
                        'endCursor'       => null,
                    ],
                    'totalCount' => 0,
                ];
            }

            $repository = $container->get($repoClass);
            $offset = $after !== null ? (int) base64_decode($after, true) + 1 : 0;

            // Try to get items with one extra for hasNextPage detection
            $items = [];
            if (method_exists($repository, 'findPaginated')) {
                $items = $repository->findPaginated($offset, $first + 1);
            } elseif (method_exists($repository, 'findAll')) {
                $all = $repository->findAll();
                $items = array_slice($all, $offset, $first + 1);
            }

            $hasNextPage = count($items) > $first;
            if ($hasNextPage) {
                array_pop($items);
            }

            $edges = [];
            foreach ($items as $i => $item) {
                $cursor = base64_encode((string) ($offset + $i));
                $edges[] = [
                    'node'   => $item,
                    'cursor' => $cursor,
                ];
            }

            return [
                'edges'    => $edges,
                'pageInfo' => [
                    'hasNextPage'     => $hasNextPage,
                    'hasPreviousPage' => $offset > 0,
                    'startCursor'     => $edges !== [] ? $edges[0]['cursor'] : null,
                    'endCursor'       => $edges !== [] ? $edges[count($edges) - 1]['cursor'] : null,
                ],
                'totalCount' => method_exists($repository, 'count') ? $repository->count() : count($items),
            ];
        };
    }
}
