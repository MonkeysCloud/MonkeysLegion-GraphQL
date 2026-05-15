<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Scanner;

use GraphQL\Type\Definition\Type;
use MonkeysLegion\Entity\Attributes\Field;
use MonkeysLegion\Entity\Attributes\Hidden;
use MonkeysLegion\Entity\Attributes\Id;
use MonkeysLegion\Entity\Attributes\ManyToOne;
use MonkeysLegion\Entity\Attributes\OneToMany;
use MonkeysLegion\Entity\Attributes\OneToOne;
use MonkeysLegion\Entity\Attributes\ManyToMany;
use MonkeysLegion\GraphQL\Type\DateTimeScalar;
use MonkeysLegion\GraphQL\Type\JsonScalar;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Maps MonkeysLegion V2 entity classes to GraphQL Object Types.
 * Understands #[Field], #[Hidden], #[Id], and Relationship attributes.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class EntityTypeMapper
{
    /** @var array<string, array<string, mixed>> */
    public private(set) array $mappings = [];

    /**
     * Map an entity class to a GraphQL type configuration.
     *
     * @param class-string $entityClass The entity FQCN
     *
     * @return array{name: string, fields: array<string, array<string, mixed>>}
     */
    public function map(string $entityClass): array
    {
        if (isset($this->mappings[$entityClass])) {
            return $this->mappings[$entityClass];
        }

        $reflection = new ReflectionClass($entityClass);
        $shortName = $reflection->getShortName();
        $fields = [];

        foreach ($reflection->getProperties() as $property) {
            // Respect #[Hidden] attribute
            if ($property->getAttributes(Hidden::class) !== []) {
                continue;
            }

            // Only map if it has #[Field], #[Id], or relationship attributes
            $isField = $property->getAttributes(Field::class) !== [] || $property->getAttributes(Id::class) !== [];
            $isRel = $this->isRelationship($property);

            if ($isField || $isRel) {
                $fieldConfig = $this->mapProperty($property, $isRel);
                if ($fieldConfig !== null) {
                    $fields[$property->getName()] = $fieldConfig;
                }
            }
        }

        $mapping = [
            'name'   => $shortName,
            'fields' => $fields,
        ];

        $this->mappings[$entityClass] = $mapping;
        return $mapping;
    }

    /**
     * Map multiple entity classes.
     *
     * @param list<class-string> $entityClasses Entity FQCNs
     *
     * @return array<string, array{name: string, fields: array<string, array<string, mixed>>}>
     */
    public function mapAll(array $entityClasses): array
    {
        $result = [];
        foreach ($entityClasses as $class) {
            $result[$class] = $this->map($class);
        }
        return $result;
    }

    private function isRelationship(ReflectionProperty $property): bool
    {
        return $property->getAttributes(ManyToOne::class) !== []
            || $property->getAttributes(OneToMany::class) !== []
            || $property->getAttributes(OneToOne::class) !== []
            || $property->getAttributes(ManyToMany::class) !== [];
    }

    /**
     * Map a property to a GraphQL field configuration.
     *
     * @param ReflectionProperty $property The property to map
     * @param bool               $isRel    Whether this is a relationship field
     *
     * @return array{type: callable(): \GraphQL\Type\Definition\Type, resolve?: callable}|null
     */
    private function mapProperty(ReflectionProperty $property, bool $isRel): ?array
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            if ($isRel && $property->getType() === null) {
                return [
                    'type' => static fn() => Type::listOf(Type::string()),
                ];
            }
            return null;
        }

        $isNullable = $type->allowsNull();

        $config = [
            'type' => function () use ($type, $isNullable, $isRel): Type {
                $graphqlType = $this->phpTypeToGraphQL($type, $isRel);
                if ($graphqlType === null) {
                    return Type::string(); // Fallback
                }
                
                $resolved = $graphqlType();
                return $isNullable ? $resolved : Type::nonNull($resolved);
            },
        ];

        if ($isRel) {
            $config['resolve'] = $this->createRelationResolver($property);
        }

        return $config;
    }

    private function createRelationResolver(ReflectionProperty $property): \Closure
    {
        $manyToOne = $property->getAttributes(ManyToOne::class);
        $oneToMany = $property->getAttributes(OneToMany::class);
        $propName = $property->getName();

        if ($manyToOne !== []) {
            $attr = $manyToOne[0]->newInstance();
            $targetEntity = $attr->targetEntity;

            return static function (mixed $root, array $args, \MonkeysLegion\GraphQL\Context\GraphQLContext $ctx) use ($targetEntity, $propName) {
                if (!is_object($root)) {
                    return null;
                }

                // If it's already hydrated, return it directly.
                $reflection = new \ReflectionProperty($root, $propName);
                if ($reflection->isInitialized($root)) {
                    $val = $reflection->getValue($root);
                    if ($val !== null && !($val instanceof \MonkeysLegion\Query\Repository\UninitializedProxy)) {
                        return $val;
                    }
                }

                // Not hydrated. We need the foreign key value.
                // Assuming naming convention like `target_id` or accessible via proxy.
                // For simplicity, we assume the root entity has a dynamic property or getter for the FK.
                // E.g., $root->{propName . '_id'}
                $fkProp = $propName . '_id';
                $fkValue = $root->$fkProp ?? null;
                
                if ($fkValue === null) {
                    return null;
                }

                $targetRef = new \ReflectionClass($targetEntity);
                $resourceAttr = $targetRef->getAttributes(\MonkeysLegion\GraphQL\Attribute\GraphQLResource::class)[0]?->newInstance();
                $repoClass = $resourceAttr?->repositoryClass ?? throw new \RuntimeException("GraphQLResource for $targetEntity must define a repositoryClass");

                $loader = $ctx->container->get(\MonkeysLegion\GraphQL\Loader\EntityDataLoader::class);
                $repository = $ctx->container->get($repoClass);

                return $loader->loadById($repository, $fkValue);
            };
        }

        if ($oneToMany !== []) {
            $attr = $oneToMany[0]->newInstance();
            $targetEntity = $attr->targetEntity;
            $mappedBy = $attr->mappedBy;

            return static function (mixed $root, array $args, \MonkeysLegion\GraphQL\Context\GraphQLContext $ctx) use ($targetEntity, $mappedBy, $propName) {
                // If already hydrated (e.g. array of objects), return them
                $reflection = new \ReflectionProperty($root, $propName);
                if ($reflection->isInitialized($root)) {
                    $val = $reflection->getValue($root);
                    if (is_array($val) && $val !== []) {
                        return $val;
                    }
                }

                // Need to load by foreign key
                $rootId = $root->id ?? null;
                if ($rootId === null) {
                    return [];
                }

                $targetRef = new \ReflectionClass($targetEntity);
                $resourceAttr = $targetRef->getAttributes(\MonkeysLegion\GraphQL\Attribute\GraphQLResource::class)[0]?->newInstance();
                $repoClass = $resourceAttr?->repositoryClass ?? throw new \RuntimeException("GraphQLResource for $targetEntity must define a repositoryClass");

                $loader = $ctx->container->get(\MonkeysLegion\GraphQL\Loader\EntityDataLoader::class);
                $repository = $ctx->container->get($repoClass);

                // For OneToMany, we query where `mappedBy_id` = root->id
                $fkColumn = $mappedBy . '_id';

                return $loader->loadByForeignKey($repository, $fkColumn, $rootId);
            };
        }

        // Fallback for OneToOne / ManyToMany (not fully implemented for demo)
        return static fn(mixed $root) => $root->$propName ?? null;
    }

    /**
     * Convert a PHP type to a GraphQL type factory.
     *
     * @param ReflectionNamedType $type  The PHP type
     * @param bool                $isRel Whether it's a relation
     *
     * @return (callable(): \GraphQL\Type\Definition\Type)|null
     */
    private function phpTypeToGraphQL(ReflectionNamedType $type, bool $isRel): ?callable
    {
        $typeName = $type->getName();

        if ($isRel) {
            if ($typeName === 'array') {
                // Will be resolved correctly during schema linking via targetEntity
                // Returning string list as temporary placeholder
                return static fn() => Type::listOf(Type::string());
            }
            // For to-one relationships, placeholder
            return static fn() => Type::string();
        }

        // Use static singletons for custom scalars to avoid duplicate type names
        static $jsonScalar = null;
        static $dateTimeScalar = null;

        return match ($typeName) {
            'int'                       => static fn() => Type::int(),
            'float'                     => static fn() => Type::float(),
            'string'                    => static fn() => Type::string(),
            'bool'                      => static fn() => Type::boolean(),
            'array'                     => static function () use (&$jsonScalar) {
                return $jsonScalar ??= new JsonScalar();
            },
            \DateTimeInterface::class,
            \DateTime::class,
            \DateTimeImmutable::class   => static function () use (&$dateTimeScalar) {
                return $dateTimeScalar ??= new DateTimeScalar();
            },
            default                     => null,
        };
    }
}
