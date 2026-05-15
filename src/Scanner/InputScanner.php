<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Scanner;

use GraphQL\Type\Definition\Type;
use MonkeysLegion\Entity\Attributes\Fillable;
use MonkeysLegion\Validation\Attributes as Assert;
use MonkeysLegion\GraphQL\Type\DateTimeScalar;
use MonkeysLegion\GraphQL\Type\JsonScalar;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Scans entities and generates GraphQL InputObjectType configurations
 * based on #[Fillable] properties, handling validation requirements.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class InputScanner
{
    /** @var array<string, array<string, mixed>> */
    public private(set) array $mappings = [];

    /**
     * Map an entity class to InputType configurations (Create and Update).
     *
     * @param class-string $entityClass The entity FQCN
     *
     * @return array{
     *     create: array{name: string, fields: array<string, array<string, mixed>>},
     *     update: array{name: string, fields: array<string, array<string, mixed>>}
     * }
     */
    public function map(string $entityClass): array
    {
        if (isset($this->mappings[$entityClass])) {
            return $this->mappings[$entityClass];
        }

        $reflection = new ReflectionClass($entityClass);
        $shortName = $reflection->getShortName();

        $createFields = [];
        $updateFields = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(Fillable::class) === []) {
                continue;
            }

            $createConfig = $this->mapProperty($property, isUpdate: false);
            if ($createConfig !== null) {
                $createFields[$property->getName()] = $createConfig;
            }

            $updateConfig = $this->mapProperty($property, isUpdate: true);
            if ($updateConfig !== null) {
                $updateFields[$property->getName()] = $updateConfig;
            }
        }

        // Add ID field for Update input
        $updateFields['id'] = [
            'type' => Type::nonNull(Type::id()),
        ];

        $mapping = [
            'create' => [
                'name'   => "Create{$shortName}Input",
                'fields' => $createFields,
            ],
            'update' => [
                'name'   => "Update{$shortName}Input",
                'fields' => $updateFields,
            ],
        ];

        $this->mappings[$entityClass] = $mapping;
        return $mapping;
    }

    /**
     * Map multiple entity classes.
     *
     * @param list<class-string> $entityClasses Entity FQCNs
     *
     * @return array<string, array{create: array<string, mixed>, update: array<string, mixed>}>
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
     * Map a property to an Input field.
     *
     * @param ReflectionProperty $property The property
     * @param bool               $isUpdate Whether this is for the Update input
     *
     * @return array{type: callable(): \GraphQL\Type\Definition\Type}|null
     */
    private function mapProperty(ReflectionProperty $property, bool $isUpdate): ?array
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        // If it's an update, all fields are optional (except ID, handled separately)
        // If it's create, check for NotBlank validation or non-nullable types
        $isNullable = true;
        if (!$isUpdate) {
            $isNullable = $type->allowsNull() && $property->getAttributes(Assert\NotBlank::class) === [];
        }

        return [
            'type' => function () use ($type, $isNullable): Type {
                $graphqlType = $this->phpTypeToGraphQL($type);
                if ($graphqlType === null) {
                    return Type::string(); // Fallback
                }
                
                $resolved = $graphqlType();
                return $isNullable ? $resolved : Type::nonNull($resolved);
            },
        ];
    }

    /**
     * @return (callable(): \GraphQL\Type\Definition\Type)|null
     */
    private function phpTypeToGraphQL(ReflectionNamedType $type): ?callable
    {
        $typeName = $type->getName();

        return match ($typeName) {
            'int'                       => static fn() => Type::int(),
            'float'                     => static fn() => Type::float(),
            'string'                    => static fn() => Type::string(),
            'bool'                      => static fn() => Type::boolean(),
            'array'                     => static fn() => new JsonScalar(),
            \DateTimeInterface::class,
            \DateTime::class,
            \DateTimeImmutable::class   => static fn() => new DateTimeScalar(),
            default                     => null, // E.g. entity relations (require ID inputs later)
        };
    }
}
