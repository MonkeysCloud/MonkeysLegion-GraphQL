<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Builder;

use GraphQL\Type\Definition\InterfaceType as WebonyxInterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType as WebonyxUnionType;
use MonkeysLegion\GraphQL\Attribute\InterfaceType;
use MonkeysLegion\GraphQL\Attribute\Type as TypeAttr;
use MonkeysLegion\GraphQL\Attribute\UnionType;
use ReflectionClass;

/**
 * Builds webonyx ObjectType, InterfaceType, and UnionType definitions from
 * classes annotated with #[Type], #[InterfaceType], or #[UnionType].
 */
final class TypeBuilder
{
    /**
     * @param FieldBuilder $fieldBuilder Builds field definitions
     */
    public function __construct(
        private readonly FieldBuilder $fieldBuilder,
    ) {}

    /**
     * Build an ObjectType from a class annotated with #[Type].
     *
     * @param class-string                                              $className  The type class name
     * @param ReflectionClass<object>                                   $reflection Reflection of the class
     * @param TypeAttr                                                  $attribute  The #[Type] attribute instance
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap    Registered type map
     * @param array<string, WebonyxInterfaceType>                       $interfaces Available interfaces
     *
     * @return ObjectType
     */
    public function buildObjectType(
        string $className,
        ReflectionClass $reflection,
        TypeAttr $attribute,
        array $typeMap = [],
        array $interfaces = [],
    ): ObjectType {
        $name = $attribute->name ?? $this->inferTypeName($reflection);

        // Determine which interfaces this type implements
        $implementedInterfaces = [];
        foreach ($reflection->getInterfaceNames() as $ifaceName) {
            if (isset($interfaces[$ifaceName])) {
                $implementedInterfaces[] = $interfaces[$ifaceName];
            }
        }

        // Also check parent classes for abstract #[InterfaceType]
        $parent = $reflection->getParentClass();
        if ($parent !== false && isset($interfaces[$parent->getName()])) {
            $implementedInterfaces[] = $interfaces[$parent->getName()];
        }

        return new ObjectType([
            'name'        => $name,
            'description' => $attribute->description,
            'fields'      => fn() => $this->fieldBuilder->buildFields($reflection, $typeMap),
            'interfaces'  => $implementedInterfaces !== [] ? $implementedInterfaces : [],
        ]);
    }

    /**
     * Build an InterfaceType from a class annotated with #[InterfaceType].
     *
     * @param class-string                                              $className  The interface class name
     * @param ReflectionClass<object>                                   $reflection Reflection of the class
     * @param InterfaceType                                             $attribute  The #[InterfaceType] attribute instance
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap    Registered type map
     * @param array<string, ObjectType>                                 $objectTypes Object types for resolveType
     *
     * @return WebonyxInterfaceType
     */
    public function buildInterfaceType(
        string $className,
        ReflectionClass $reflection,
        InterfaceType $attribute,
        array $typeMap = [],
        array $objectTypes = [],
    ): WebonyxInterfaceType {
        $name = $attribute->name ?? $reflection->getShortName();

        return new WebonyxInterfaceType([
            'name'        => $name,
            'description' => $attribute->description,
            'fields'      => fn() => $this->fieldBuilder->buildFields($reflection, $typeMap),
            'resolveType' => static function (mixed $value) use ($objectTypes): ?ObjectType {
                // Try to match by class name
                $valueClass = is_object($value) ? $value::class : null;
                if ($valueClass !== null && isset($objectTypes[$valueClass])) {
                    return $objectTypes[$valueClass];
                }

                return null;
            },
        ]);
    }

    /**
     * Build a UnionType from a class annotated with #[UnionType].
     *
     * @param class-string                $className  The union class name
     * @param ReflectionClass<object>     $reflection Reflection of the class
     * @param UnionType                   $attribute  The #[UnionType] attribute instance
     * @param array<string, ObjectType>   $objectTypes Map of FQCN → ObjectType
     *
     * @return WebonyxUnionType
     */
    public function buildUnionType(
        string $className,
        ReflectionClass $reflection,
        UnionType $attribute,
        array $objectTypes = [],
    ): WebonyxUnionType {
        $memberTypes = [];
        foreach ($attribute->types as $memberClass) {
            if (isset($objectTypes[$memberClass])) {
                $memberTypes[] = $objectTypes[$memberClass];
            }
        }

        return new WebonyxUnionType([
            'name'        => $attribute->name,
            'description' => $attribute->description,
            'types'       => $memberTypes,
            'resolveType' => static function (mixed $value) use ($className, $objectTypes): ?ObjectType {
                // Delegate to the union class's resolveType method
                if (method_exists($className, 'resolveType')) {
                    /** @var class-string $resolvedClass */
                    $resolvedClass = $className::resolveType($value);
                    return $objectTypes[$resolvedClass] ?? null;
                }

                // Fallback: match by class name
                $valueClass = is_object($value) ? $value::class : null;
                if ($valueClass !== null && isset($objectTypes[$valueClass])) {
                    return $objectTypes[$valueClass];
                }

                return null;
            },
        ]);
    }

    /**
     * Infer the GraphQL type name from a class name.
     *
     * Strips the "Type" suffix if present.
     *
     * @param ReflectionClass<object> $reflection The class reflection
     *
     * @return string
     */
    private function inferTypeName(ReflectionClass $reflection): string
    {
        $shortName = $reflection->getShortName();
        if (str_ends_with($shortName, 'Type') && $shortName !== 'Type') {
            return substr($shortName, 0, -4);
        }
        return $shortName;
    }
}
