<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Builder;

use GraphQL\Type\Definition\Type;
use MonkeysLegion\GraphQL\Attribute\GraphQLResource;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use Psr\Container\ContainerInterface;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Automatically builds CRUD operations for #[GraphQLResource] entities.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class AutoCrudBuilder
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Build auto-generated query fields.
     *
     * @param list<class-string> $resources Entities with #[GraphQLResource]
     * @param callable(class-string): \GraphQL\Type\Definition\Type $typeResolver
     *
     * @return array<string, array<string, mixed>>
     */
    public function buildQueries(array $resources, callable $typeResolver): array
    {
        $fields = [];
        $container = $this->container;

        foreach ($resources as $resource) {
            $reflection = new \ReflectionClass($resource);
            $shortName = $reflection->getShortName();

            /** @var GraphQLResource $attr */
            $attr = $reflection->getAttributes(GraphQLResource::class)[0]->newInstance();

            $baseQueryName = $attr->queryName ?? strtolower($shortName);
            $graphqlType = $typeResolver($resource);

            if ($attr->hasOperation('find')) {
                $fields[$baseQueryName] = [
                    'type' => $graphqlType,
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                    ],
                    'resolve' => static function (mixed $root, array $args, GraphQLContext $ctx) use ($container, $attr, $resource) {
                        $repository = $container->get($attr->repositoryClass ?? throw new \RuntimeException("GraphQLResource for $resource must define a repositoryClass"));
                        return $repository->find((int) $args['id']);
                    },
                ];
            }

            if ($attr->hasOperation('list')) {
                $listName = $baseQueryName . 's'; // simple plural

                $filterScanner = new \MonkeysLegion\GraphQL\Scanner\FilterScanner();
                $filterConfig = $filterScanner->map($resource);

                $listArgs = [];
                if ($attr->paginateList) {
                    $listArgs['first'] = Type::int();
                    $listArgs['after'] = Type::string();
                }

                if ($filterConfig['where'] !== null) {
                    $listArgs['where'] = $filterConfig['where'];
                }
                if ($filterConfig['orderBy'] !== null) {
                    $listArgs['orderBy'] = $filterConfig['orderBy'];
                }
                if ($filterConfig['search']) {
                    $listArgs['search'] = Type::string();
                }

                if ($attr->paginateList && $graphqlType instanceof \GraphQL\Type\Definition\ObjectType) {
                    $connectionType = \MonkeysLegion\GraphQL\Type\ConnectionType::create($shortName, $graphqlType);
                    $fields[$listName] = [
                        'type' => $connectionType,
                        'args' => $listArgs,
                        'resolve' => static function (mixed $root, array $args, GraphQLContext $ctx) use ($container, $attr, $resource) {
                            $repository = $container->get($attr->repositoryClass ?? throw new \RuntimeException("GraphQLResource for $resource must define a repositoryClass"));
                            return \MonkeysLegion\GraphQL\Resolver\PaginatorResolver::resolve($repository, $args);
                        },
                    ];
                } else {
                    $fields[$listName] = [
                        'type' => Type::listOf(Type::nonNull($graphqlType)),
                        'args' => $listArgs,
                        'resolve' => static function (mixed $root, array $args, GraphQLContext $ctx) use ($container, $attr, $resource) {
                            $repository = $container->get($attr->repositoryClass ?? throw new \RuntimeException("GraphQLResource for $resource must define a repositoryClass"));
                            return \MonkeysLegion\GraphQL\Resolver\FilterResolver::resolveAll($repository, $args);
                        },
                    ];
                }
            }
        }

        return $fields;
    }

    /**
     * Build auto-generated mutation fields.
     *
     * @param list<class-string> $resources Entities with #[GraphQLResource]
     * @param callable(class-string): \GraphQL\Type\Definition\Type $typeResolver
     * @param callable(class-string, bool): \GraphQL\Type\Definition\InputObjectType $inputResolver
     *
     * @return array<string, array<string, mixed>>
     */
    public function buildMutations(array $resources, callable $typeResolver, callable $inputResolver): array
    {
        $fields = [];
        $container = $this->container;

        foreach ($resources as $resource) {
            $reflection = new \ReflectionClass($resource);
            $shortName = $reflection->getShortName();

            /** @var GraphQLResource $attr */
            $attr = $reflection->getAttributes(GraphQLResource::class)[0]->newInstance();

            $graphqlType = $typeResolver($resource);

            // Scan validation rules for this entity
            $validationScanner = new \MonkeysLegion\GraphQL\Scanner\ValidationScanner();
            $validationRules = $validationScanner->scan($resource);

            if ($attr->hasOperation('create')) {
                $inputType = $inputResolver($resource, false);
                $createRules = $validationRules['create'];
                $createResolver = static function (mixed $root, array $args, GraphQLContext $ctx) use ($container, $attr, $resource, $createRules) {
                    // Validate input before persisting
                    if (!$createRules->isEmpty()) {
                        $validator = new \MonkeysLegion\GraphQL\Validation\InputValidator();
                        $validator->validate($args['input'], $createRules);
                    }

                    $repository = $container->get($attr->repositoryClass ?? throw new \RuntimeException("GraphQLResource for $resource must define a repositoryClass"));
                    $entity = new $resource();
                    foreach ($args['input'] as $key => $value) {
                        $entity->$key = $value;
                    }
                    $repository->persist($entity);
                    $repository->flush();
                    return $entity;
                };

                $fields['create' . $shortName] = [
                    'type' => $graphqlType,
                    'args' => [
                        'input' => Type::nonNull($inputType),
                    ],
                    'resolve' => \MonkeysLegion\GraphQL\Security\SecurityEnforcer::wrapCrud($createResolver, $resource, 'create'),
                ];
            }

            if ($attr->hasOperation('update')) {
                $inputType = $inputResolver($resource, true);
                $updateRules = $validationRules['update'];
                $updateResolver = static function (mixed $root, array $args, GraphQLContext $ctx) use ($container, $attr, $resource, $updateRules) {
                    // Validate input before persisting
                    if (!$updateRules->isEmpty()) {
                        $validator = new \MonkeysLegion\GraphQL\Validation\InputValidator();
                        $validator->validate($args['input'], $updateRules);
                    }

                    $repository = $container->get($attr->repositoryClass ?? throw new \RuntimeException("GraphQLResource for $resource must define a repositoryClass"));
                    $input = $args['input'];
                    $entity = $repository->findOrFail((int) $input['id']);
                    foreach ($input as $key => $value) {
                        if ($key !== 'id') {
                            $entity->$key = $value;
                        }
                    }
                    $repository->flush();
                    return $entity;
                };

                $fields['update' . $shortName] = [
                    'type' => $graphqlType,
                    'args' => [
                        'input' => Type::nonNull($inputType),
                    ],
                    'resolve' => \MonkeysLegion\GraphQL\Security\SecurityEnforcer::wrapCrud($updateResolver, $resource, 'update'),
                ];
            }

            if ($attr->hasOperation('delete')) {
                $deleteResolver = static function (mixed $root, array $args, GraphQLContext $ctx) use ($container, $attr, $resource) {
                    $repository = $container->get($attr->repositoryClass ?? throw new \RuntimeException("GraphQLResource for $resource must define a repositoryClass"));
                    $entity = $repository->findOrFail((int) $args['id']);
                    $repository->remove($entity);
                    $repository->flush();
                    return true;
                };

                $fields['delete' . $shortName] = [
                    'type' => Type::boolean(),
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                    ],
                    'resolve' => \MonkeysLegion\GraphQL\Security\SecurityEnforcer::wrapCrud($deleteResolver, $resource, 'delete'),
                ];
            }
        }

        return $fields;
    }
}
