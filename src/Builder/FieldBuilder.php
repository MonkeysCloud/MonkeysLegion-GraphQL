<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Builder;

use GraphQL\Type\Definition\Type;
use MonkeysLegion\GraphQL\Attribute\Arg;
use MonkeysLegion\GraphQL\Attribute\Field;
use MonkeysLegion\GraphQL\Attribute\Middleware;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Builds webonyx field definition arrays from #[Field]-annotated methods and properties.
 *
 * For methods: the first parameter is the parent/root object. Additional parameters
 * are resolved as GraphQL arguments (if #[Arg]), DI services, or GraphQLContext.
 */
final class FieldBuilder
{
    /**
     * @param ArgumentBuilder    $argumentBuilder Builds argument configs
     * @param ContainerInterface $container       DI container for resolver instantiation
     */
    public function __construct(
        private readonly ArgumentBuilder $argumentBuilder,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Build all field definitions for a #[Type] class.
     *
     * @param ReflectionClass<object>                                   $reflection The type class reflection
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap    Registered type map for lookups
     *
     * @return array<string, array<string, mixed>>
     */
    public function buildFields(ReflectionClass $reflection, array $typeMap = []): array
    {
        $fields = [];

        // Process #[Field] methods
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $fieldAttr = $this->getFieldAttribute($method);
            if ($fieldAttr === null) {
                continue;
            }

            $fieldName = $fieldAttr->name ?? $method->getName();
            $fields[$fieldName] = $this->buildMethodField($reflection, $method, $fieldAttr, $typeMap);
        }

        // Process #[Field] properties
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $fieldAttr = $this->getPropertyFieldAttribute($property);
            if ($fieldAttr === null) {
                continue;
            }

            $fieldName = $fieldAttr->name ?? $property->getName();
            $fields[$fieldName] = $this->buildPropertyField($property, $fieldAttr, $typeMap);
        }

        return $fields;
    }

    /**
     * Build field config from a #[Field] method.
     *
     * @param ReflectionClass<object>                                   $classRef  The owning class reflection
     * @param ReflectionMethod                                          $method    The method
     * @param Field                                                     $fieldAttr The attribute instance
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap    Type map
     *
     * @return array<string, mixed>
     */
    private function buildMethodField(
        ReflectionClass $classRef,
        ReflectionMethod $method,
        Field $fieldAttr,
        array $typeMap,
    ): array {
        $config = [];

        // Resolve return type
        $config['type'] = $this->resolveReturnType($method, $fieldAttr, $typeMap);

        if ($fieldAttr->description !== null) {
            $config['description'] = $fieldAttr->description;
        }
        if ($fieldAttr->deprecationReason !== null) {
            $config['deprecationReason'] = $fieldAttr->deprecationReason;
        }
        if ($fieldAttr->complexity !== null) {
            $config['complexity'] = $fieldAttr->complexity;
        }

        // Build arguments (skip first param = parent object)
        $args = $this->argumentBuilder->buildFromMethod($method, 1, $typeMap);
        if ($args !== []) {
            $config['args'] = $args;
        }

        // Collect middleware
        $middlewareList = $this->collectMiddleware($method);

        // Build resolver closure
        $className = $classRef->getName();
        $methodName = $method->getName();
        $container = $this->container;
        $argBuilder = $this->argumentBuilder;

        $config['resolve'] = static function (mixed $root, array $args, GraphQLContext $context) use (
            $className,
            $methodName,
            $container,
            $method,
            $middlewareList,
        ): mixed {
            $instance = $container->get($className);

            // Build method arguments: parent, then context/services/args
            $callArgs = [$root];
            $params = $method->getParameters();

            for ($i = 1; $i < count($params); $i++) {
                $param = $params[$i];
                $callArgs[] = self::resolveParameter($param, $args, $context, $container);
            }

            // If middleware is present, wrap the resolver
            if ($middlewareList !== []) {
                return self::runMiddlewareChain(
                    $middlewareList,
                    $root,
                    $args,
                    $context,
                    static fn() => $instance->$methodName(...$callArgs),
                );
            }

            return $instance->$methodName(...$callArgs);
        };

        return $config;
    }

    /**
     * Build field config from a #[Field] property.
     *
     * @param ReflectionProperty                                        $property  The property
     * @param Field                                                     $fieldAttr The attribute instance
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap    Type map
     *
     * @return array<string, mixed>
     */
    private function buildPropertyField(
        ReflectionProperty $property,
        Field $fieldAttr,
        array $typeMap,
    ): array {
        $config = [];

        // Resolve property type
        if ($fieldAttr->type !== null) {
            $config['type'] = $this->argumentBuilder->parseTypeString($fieldAttr->type, $typeMap);
        } else {
            $refType = $property->getType();
            if ($refType instanceof ReflectionNamedType) {
                $graphqlType = $this->argumentBuilder->phpTypeToGraphQL($refType->getName(), $typeMap);
                $config['type'] = $refType->allowsNull() ? ($graphqlType ?? Type::string()) : Type::nonNull($graphqlType ?? Type::string());
            } else {
                $config['type'] = Type::string();
            }
        }

        if ($fieldAttr->description !== null) {
            $config['description'] = $fieldAttr->description;
        }
        if ($fieldAttr->deprecationReason !== null) {
            $config['deprecationReason'] = $fieldAttr->deprecationReason;
        }

        $propName = $property->getName();
        $config['resolve'] = static function (mixed $root) use ($propName): mixed {
            if (is_array($root)) {
                return $root[$propName] ?? null;
            }
            if (is_object($root) && property_exists($root, $propName)) {
                return $root->$propName;
            }
            return null;
        };

        return $config;
    }

    /**
     * Resolve the GraphQL return type from a method's return type hint.
     *
     * @param ReflectionMethod                                          $method    The method
     * @param Field                                                     $fieldAttr The attribute
     * @param array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType> $typeMap    Type map
     *
     * @return \GraphQL\Type\Definition\Type
     */
    private function resolveReturnType(ReflectionMethod $method, Field $fieldAttr, array $typeMap): \GraphQL\Type\Definition\Type
    {
        if ($fieldAttr->type !== null) {
            return $this->argumentBuilder->parseTypeString($fieldAttr->type, $typeMap);
        }

        $returnType = $method->getReturnType();
        if (!$returnType instanceof ReflectionNamedType) {
            return Type::string();
        }

        $typeName = $returnType->getName();
        $baseType = $this->argumentBuilder->phpTypeToGraphQL($typeName, $typeMap) ?? Type::string();

        return $returnType->allowsNull() ? $baseType : Type::nonNull($baseType);
    }

    /**
     * Resolve a method parameter to its runtime value.
     *
     * @param \ReflectionParameter  $param     The parameter
     * @param array<string, mixed>  $args      GraphQL arguments
     * @param GraphQLContext        $context   The execution context
     * @param ContainerInterface    $container DI container
     *
     * @return mixed
     */
    private static function resolveParameter(
        \ReflectionParameter $param,
        array $args,
        GraphQLContext $context,
        ContainerInterface $container,
    ): mixed {
        $type = $param->getType();
        $paramName = $param->getName();

        // Check if parameter is GraphQLContext
        if ($type instanceof ReflectionNamedType && $type->getName() === GraphQLContext::class) {
            return $context;
        }

        // Check if parameter has #[Arg] or is in the args array
        $argAttr = $param->getAttributes(Arg::class);
        $argName = $paramName;
        if ($argAttr !== []) {
            $instance = $argAttr[0]->newInstance();
            $argName = $instance->name ?? $paramName;
        }

        if (array_key_exists($argName, $args)) {
            return $args[$argName];
        }

        // Try DI container for service types
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if ($container->has($typeName)) {
                return $container->get($typeName);
            }
        }

        // Default value
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        return null;
    }

    /**
     * Collect middleware class names from a method's #[Middleware] attributes.
     *
     * @param ReflectionMethod $method The method
     *
     * @return array<string>
     */
    private function collectMiddleware(ReflectionMethod $method): array
    {
        $middlewareList = [];
        $attrs = $method->getAttributes(Middleware::class);
        foreach ($attrs as $attr) {
            $mw = $attr->newInstance();
            $middlewareList = array_merge($middlewareList, $mw->middleware);
        }
        return $middlewareList;
    }

    /**
     * Run a middleware chain around a resolver.
     *
     * @param array<string>    $middlewareClasses Middleware class names
     * @param mixed            $root              Parent object
     * @param array<string, mixed> $args          GraphQL arguments
     * @param GraphQLContext   $context           Execution context
     * @param \Closure         $resolver          The final resolver
     *
     * @return mixed
     */
    private static function runMiddlewareChain(
        array $middlewareClasses,
        mixed $root,
        array $args,
        GraphQLContext $context,
        \Closure $resolver,
    ): mixed {
        $chain = $resolver;

        foreach (array_reverse($middlewareClasses) as $mwClass) {
            $next = $chain;
            $chain = static function () use ($mwClass, $root, $args, $context, $next): mixed {
                $mw = $context->container->get($mwClass);
                if (method_exists($mw, 'handle')) {
                    return $mw->handle($root, $args, $context, $next);
                }
                return $next();
            };
        }

        return $chain();
    }

    /**
     * Get the #[Field] attribute from a method.
     *
     * @param ReflectionMethod $method The method to inspect
     *
     * @return Field|null
     */
    private function getFieldAttribute(ReflectionMethod $method): ?Field
    {
        $attrs = $method->getAttributes(Field::class);
        return $attrs !== [] ? $attrs[0]->newInstance() : null;
    }

    /**
     * Get the #[Field] attribute from a property.
     *
     * @param ReflectionProperty $property The property to inspect
     *
     * @return Field|null
     */
    private function getPropertyFieldAttribute(ReflectionProperty $property): ?Field
    {
        $attrs = $property->getAttributes(Field::class);
        return $attrs !== [] ? $attrs[0]->newInstance() : null;
    }
}
