<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Builder;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use MonkeysLegion\GraphQL\Attribute\InputType;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Builds webonyx InputObjectType definitions from classes annotated with #[InputType].
 *
 * Constructor parameters of the class become input fields. The class should
 * use readonly promoted properties.
 */
final class InputTypeBuilder
{
    /**
     * @param ArgumentBuilder $argumentBuilder For type resolution
     */
    public function __construct(
        private readonly ArgumentBuilder $argumentBuilder,
    ) {}

    /**
     * Build an InputObjectType from a class annotated with #[InputType].
     *
     * @param class-string                                              $className  The input type class name
     * @param ReflectionClass<object>                                   $reflection Reflection of the class
     * @param InputType                                                 $attribute  The #[InputType] attribute instance
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap    Registered type map
     *
     * @return InputObjectType
     */
    public function build(
        string $className,
        ReflectionClass $reflection,
        InputType $attribute,
        array $typeMap = [],
    ): InputObjectType {
        $name = $attribute->name ?? $reflection->getShortName();
        $fields = [];

        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();
                $fieldConfig = $this->buildFieldFromParam($param, $typeMap);
                if ($fieldConfig !== null) {
                    $fields[$paramName] = $fieldConfig;
                }
            }
        }

        return new InputObjectType([
            'name'        => $name,
            'description' => $attribute->description,
            'fields'      => $fields,
        ]);
    }

    /**
     * Build an input field config from a constructor parameter.
     *
     * @param \ReflectionParameter                                      $param   The constructor parameter
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap Type map
     *
     * @return array{type: \GraphQL\Type\Definition\Type, defaultValue?: mixed}|null
     */
    private function buildFieldFromParam(\ReflectionParameter $param, array $typeMap): ?array
    {
        $refType = $param->getType();
        if (!$refType instanceof ReflectionNamedType) {
            return ['type' => Type::string()];
        }

        $typeName = $refType->getName();
        $baseType = $this->argumentBuilder->phpTypeToGraphQL($typeName, $typeMap);
        if ($baseType === null) {
            // If it's a class in the type map, try resolving
            $baseType = $typeMap[$typeName] ?? Type::string();
        }

        $graphqlType = $refType->allowsNull() ? $baseType : Type::nonNull($baseType);

        $config = ['type' => $graphqlType];

        if ($param->isDefaultValueAvailable()) {
            $config['defaultValue'] = $param->getDefaultValue();
        }

        return $config;
    }
}
