<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Builder;

use GraphQL\Type\Definition\Type;
use MonkeysLegion\GraphQL\Attribute\Arg;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Builds webonyx GraphQL argument configurations from method parameters.
 *
 * Inspects #[Arg] attributes and PHP type hints on resolver method parameters
 * to produce argument definition arrays for webonyx/graphql-php.
 */
final class ArgumentBuilder
{
    /**
     * Build argument definitions from the parameters of a resolver method.
     *
     * Parameters typed as GraphQLContext or services (classes without #[Arg])
     * are skipped — only scalar/input-typed parameters with #[Arg] become args.
     *
     * @param ReflectionMethod                                          $method    The resolver method
     * @param int                                                       $skipFirst How many leading params to skip (e.g., 1 for parent obj)
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap   Registered type map for lookup
     *
     * @return array<string, array{type: \GraphQL\Type\Definition\Type, description?: string, defaultValue?: mixed}>
     */
    public function buildFromMethod(ReflectionMethod $method, int $skipFirst = 0, array $typeMap = []): array
    {
        $args = [];
        $params = $method->getParameters();

        for ($i = $skipFirst; $i < count($params); $i++) {
            $param = $params[$i];
            $argAttr = $this->getArgAttribute($param);

            // Skip parameters that are services or GraphQLContext (no #[Arg])
            if ($argAttr === null && $this->isServiceParameter($param)) {
                continue;
            }

            $argConfig = $this->buildArgConfig($param, $argAttr, $typeMap);
            if ($argConfig !== null) {
                $name = $argAttr?->name ?? $param->getName();
                $args[$name] = $argConfig;
            }
        }

        return $args;
    }

    /**
     * Build a single argument config from a parameter.
     *
     * @param ReflectionParameter                                       $param   The parameter
     * @param Arg|null                                                  $argAttr The #[Arg] attribute instance, if present
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap Registered type map
     *
     * @return array{type: \GraphQL\Type\Definition\Type, description?: string, defaultValue?: mixed}|null
     */
    private function buildArgConfig(ReflectionParameter $param, ?Arg $argAttr, array $typeMap): ?array
    {
        $graphqlType = $this->resolveType($param, $argAttr, $typeMap);
        if ($graphqlType === null) {
            return null;
        }

        $config = ['type' => $graphqlType];

        $description = $argAttr?->description;
        if ($description !== null) {
            $config['description'] = $description;
        }

        if ($argAttr !== null && $argAttr->hasDefaultValue()) {
            $config['defaultValue'] = $argAttr->defaultValue;
        } elseif ($param->isDefaultValueAvailable()) {
            $config['defaultValue'] = $param->getDefaultValue();
        }

        return $config;
    }

    /**
     * Resolve the GraphQL type for a parameter from its PHP type hint and/or #[Arg] override.
     *
     * @param ReflectionParameter                                       $param   The parameter
     * @param Arg|null                                                  $argAttr The #[Arg] attribute instance
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap Registered type map
     *
     * @return \GraphQL\Type\Definition\Type|null
     */
    private function resolveType(ReflectionParameter $param, ?Arg $argAttr, array $typeMap): ?\GraphQL\Type\Definition\Type
    {
        // Explicit type override from #[Arg]
        if ($argAttr?->type !== null) {
            return $this->parseTypeString($argAttr->type, $typeMap);
        }

        $refType = $param->getType();
        if (!$refType instanceof ReflectionNamedType) {
            return Type::string(); // fallback
        }

        $nullable = $refType->allowsNull() || ($argAttr?->nullable ?? false);
        $typeName = $refType->getName();

        $baseType = $this->phpTypeToGraphQL($typeName, $typeMap);
        if ($baseType === null) {
            return null;
        }

        return $nullable ? $baseType : Type::nonNull($baseType);
    }

    /**
     * Map a PHP type name to a GraphQL scalar or named type.
     *
     * @param string                                                    $phpType PHP type name
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap Registered type map
     *
     * @return \GraphQL\Type\Definition\Type|null
     */
    public function phpTypeToGraphQL(string $phpType, array $typeMap = []): ?\GraphQL\Type\Definition\Type
    {
        return match ($phpType) {
            'int'    => Type::int(),
            'float'  => Type::float(),
            'string' => Type::string(),
            'bool'   => Type::boolean(),
            'array'  => Type::string(), // arrays need explicit #[Arg(type:)] override
            default  => $typeMap[$phpType] ?? null,
        };
    }

    /**
     * Parse a GraphQL type string like '[Post!]!' into a webonyx Type tree.
     *
     * Supports: String, Int, Float, Boolean, ID, NonNull (!), ListOf ([]).
     *
     * @param string                                                    $typeString GraphQL type notation
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap     Named type map
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function parseTypeString(string $typeString, array $typeMap = []): \GraphQL\Type\Definition\Type
    {
        $str = trim($typeString);

        // Handle NonNull wrapper
        $nonNull = false;
        if (str_ends_with($str, '!')) {
            $nonNull = true;
            $str = substr($str, 0, -1);
        }

        // Handle List wrapper
        if (str_starts_with($str, '[') && str_ends_with($str, ']')) {
            $inner = substr($str, 1, -1);
            $innerType = $this->parseTypeString($inner, $typeMap);
            $listType = Type::listOf($innerType);
            return $nonNull ? Type::nonNull($listType) : $listType;
        }

        // Resolve base type
        $baseType = match ($str) {
            'String'  => Type::string(),
            'Int'     => Type::int(),
            'Float'   => Type::float(),
            'Boolean' => Type::boolean(),
            'ID'      => Type::id(),
            default   => $typeMap[$str] ?? Type::string(),
        };

        return $nonNull ? Type::nonNull($baseType) : $baseType;
    }

    /**
     * Retrieve the #[Arg] attribute from a parameter, if present.
     *
     * @param ReflectionParameter $param The parameter to inspect
     *
     * @return Arg|null
     */
    private function getArgAttribute(ReflectionParameter $param): ?Arg
    {
        $attrs = $param->getAttributes(Arg::class);
        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Determine if a parameter is a service/context parameter (not an argument).
     *
     * @param ReflectionParameter $param The parameter to check
     *
     * @return bool
     */
    private function isServiceParameter(ReflectionParameter $param): bool
    {
        $type = $param->getType();
        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        $name = $type->getName();

        // Built-in types are never services
        if ($type->isBuiltin()) {
            return false;
        }

        // Classes are treated as services unless they have #[Arg]
        return class_exists($name) || interface_exists($name);
    }
}
