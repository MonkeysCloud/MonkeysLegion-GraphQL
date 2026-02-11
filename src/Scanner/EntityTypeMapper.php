<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Scanner;

use GraphQL\Type\Definition\Type;

/**
 * Maps MonkeysLegion entity classes to GraphQL types automatically.
 *
 * Scans entity properties (with Column/Id attributes) and generates
 * GraphQL object type configurations for CRUD auto-generation.
 */
final class EntityTypeMapper
{
    /** @var array<string, array<string, mixed>> Cached entity mappings */
    private array $mappings = [];

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

        $reflection = new \ReflectionClass($entityClass);
        $shortName = $reflection->getShortName();
        $fields = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $fieldConfig = $this->mapProperty($property);
            if ($fieldConfig !== null) {
                $fields[$property->getName()] = $fieldConfig;
            }
        }

        // Also check protected/private with Column/Id attributes
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE) as $property) {
            $hasColumn = $property->getAttributes(
                class: 'MonkeysLegion\\Entity\\Attribute\\Column',
                flags: \ReflectionAttribute::IS_INSTANCEOF,
            );
            $hasId = $property->getAttributes(
                class: 'MonkeysLegion\\Entity\\Attribute\\Id',
                flags: \ReflectionAttribute::IS_INSTANCEOF,
            );

            if ($hasColumn !== [] || $hasId !== []) {
                $fieldConfig = $this->mapProperty($property);
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
     * @param array<class-string> $entityClasses Entity FQCNs
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

    /**
     * Map a property to a GraphQL field configuration.
     *
     * @param \ReflectionProperty $property The property to map
     *
     * @return array{type: callable(): \GraphQL\Type\Definition\Type}|null
     */
    private function mapProperty(\ReflectionProperty $property): ?array
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }

        $graphqlType = $this->phpTypeToGraphQL($type);
        if ($graphqlType === null) {
            return null;
        }

        $isNullable = $type->allowsNull();

        return [
            'type' => static function () use ($graphqlType, $isNullable): \GraphQL\Type\Definition\Type {
                $resolved = $graphqlType();
                return $isNullable ? $resolved : Type::nonNull($resolved);
            },
        ];
    }

    /**
     * Convert a PHP type to a GraphQL type factory.
     *
     * @param \ReflectionNamedType $type The PHP type
     *
     * @return (callable(): \GraphQL\Type\Definition\Type)|null
     */
    private function phpTypeToGraphQL(\ReflectionNamedType $type): ?callable
    {
        $typeName = $type->getName();

        return match ($typeName) {
            'int'                      => static fn() => Type::int(),
            'float'                    => static fn() => Type::float(),
            'string'                   => static fn() => Type::string(),
            'bool'                     => static fn() => Type::boolean(),
            'array'                    => static fn() => Type::string(), // Fallback
            \DateTimeInterface::class,
            \DateTime::class,
            \DateTimeImmutable::class   => static fn() => new \MonkeysLegion\GraphQL\Type\DateTimeScalar(),
            default                    => null,
        };
    }
}
