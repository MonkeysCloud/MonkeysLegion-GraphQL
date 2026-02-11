<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Builder;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use MonkeysLegion\GraphQL\Attribute\Enum as EnumAttr;
use MonkeysLegion\GraphQL\Attribute\InputType as InputTypeAttr;
use MonkeysLegion\GraphQL\Attribute\InterfaceType as InterfaceTypeAttr;
use MonkeysLegion\GraphQL\Attribute\Middleware;
use MonkeysLegion\GraphQL\Attribute\Mutation;
use MonkeysLegion\GraphQL\Attribute\Query;
use MonkeysLegion\GraphQL\Attribute\Subscription;
use MonkeysLegion\GraphQL\Attribute\Type as TypeAttr;
use MonkeysLegion\GraphQL\Attribute\UnionType as UnionTypeAttr;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Attribute\Arg;
use MonkeysLegion\GraphQL\Scanner\AttributeScanner;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * Orchestrates the complete GraphQL schema build from scanned attribute metadata.
 *
 * Delegates to TypeBuilder, FieldBuilder, EnumBuilder, InputTypeBuilder,
 * and ArgumentBuilder to produce a complete webonyx Schema object.
 */
final class SchemaBuilder
{
    /** @var array<string, \GraphQL\Type\Definition\NamedType&\GraphQL\Type\Definition\Type> */
    private array $typeMap = [];

    /**
     * @param AttributeScanner   $scanner         Discovers annotated classes
     * @param TypeBuilder        $typeBuilder      Builds ObjectType/InterfaceType/UnionType
     * @param EnumBuilder        $enumBuilder      Builds EnumType
     * @param InputTypeBuilder   $inputTypeBuilder Builds InputObjectType
     * @param ArgumentBuilder    $argumentBuilder  Builds argument configs
     * @param ContainerInterface $container        DI container
     */
    public function __construct(
        private readonly AttributeScanner $scanner,
        private readonly TypeBuilder $typeBuilder,
        private readonly EnumBuilder $enumBuilder,
        private readonly InputTypeBuilder $inputTypeBuilder,
        private readonly ArgumentBuilder $argumentBuilder,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Build the complete GraphQL schema by scanning the given directories.
     *
     * @param array<string> $directories Absolute paths to scan for GraphQL classes
     *
     * @return Schema
     */
    public function build(array $directories): Schema
    {
        $this->typeMap = [];

        $scanResult = $this->scanner->scan($directories);

        // 1. Build enums first (they may be referenced by other types)
        $this->buildEnums($scanResult['enums']);

        // 2. Build input types
        $this->buildInputTypes($scanResult['inputs']);

        // 3. Build interfaces
        /** @var array<string, \GraphQL\Type\Definition\InterfaceType> $interfaces */
        $interfaces = $this->buildInterfaces($scanResult['interfaces']);

        // 4. Build object types
        /** @var array<string, ObjectType> $objectTypes */
        $objectTypes = $this->buildObjectTypes($scanResult['types'], $interfaces);

        // 5. Build union types
        $this->buildUnionTypes($scanResult['unions'], $objectTypes);

        // 6. Build root query type
        $queryType = $this->buildRootType('Query', $scanResult['queries'], Query::class);

        // 7. Build root mutation type (optional)
        $mutationType = $scanResult['mutations'] !== []
            ? $this->buildRootType('Mutation', $scanResult['mutations'], Mutation::class)
            : null;

        // 8. Build root subscription type (optional)
        $subscriptionType = $scanResult['subscriptions'] !== []
            ? $this->buildSubscriptionType($scanResult['subscriptions'])
            : null;

        $config = SchemaConfig::create()
            ->setQuery($queryType)
            ->setMutation($mutationType)
            ->setSubscription($subscriptionType)
            ->setTypes(array_values($this->typeMap));

        return new Schema($config);
    }

    /**
     * Get the built type map (useful for tests and debugging).
     *
     * @return array<string, \GraphQL\Type\Definition\NamedType&\GraphQL\Type\Definition\Type>
     */
    public function getTypeMap(): array
    {
        return $this->typeMap;
    }

    /**
     * Build EnumType definitions for all discovered enum classes.
     *
     * @param array<class-string> $enumClasses Discovered enum class names
     *
     * @return void
     */
    private function buildEnums(array $enumClasses): void
    {
        foreach ($enumClasses as $className) {
            try {
                $reflection = new ReflectionClass($className);
                $attrs = $reflection->getAttributes(EnumAttr::class);
                if ($attrs === []) {
                    continue;
                }
                $attr = $attrs[0]->newInstance();
                $enumType = $this->enumBuilder->build($className, $reflection, $attr);
                $this->typeMap[$className] = $enumType;
                $this->typeMap[$enumType->name()] = $enumType;
            } catch (\ReflectionException) {
                continue;
            }
        }
    }

    /**
     * Build InputObjectType definitions for all discovered input classes.
     *
     * @param array<class-string> $inputClasses Discovered input class names
     *
     * @return void
     */
    private function buildInputTypes(array $inputClasses): void
    {
        foreach ($inputClasses as $className) {
            try {
                $reflection = new ReflectionClass($className);
                $attrs = $reflection->getAttributes(InputTypeAttr::class);
                if ($attrs === []) {
                    continue;
                }
                $attr = $attrs[0]->newInstance();
                $inputType = $this->inputTypeBuilder->build($className, $reflection, $attr, $this->typeMap);
                $this->typeMap[$className] = $inputType;
                $this->typeMap[$inputType->name()] = $inputType;
            } catch (\ReflectionException) {
                continue;
            }
        }
    }

    /**
     * Build InterfaceType definitions for all discovered interface classes.
     *
     * @param array<class-string> $interfaceClasses Discovered interface class names
     *
     * @return array<string, \GraphQL\Type\Definition\InterfaceType>
     */
    private function buildInterfaces(array $interfaceClasses): array
    {
        /** @var array<string, \GraphQL\Type\Definition\InterfaceType> $interfaces */
        $interfaces = [];

        foreach ($interfaceClasses as $className) {
            try {
                $reflection = new ReflectionClass($className);
                $attrs = $reflection->getAttributes(InterfaceTypeAttr::class);
                if ($attrs === []) {
                    continue;
                }
                $attr = $attrs[0]->newInstance();
                $ifaceType = $this->typeBuilder->buildInterfaceType(
                    $className,
                    $reflection,
                    $attr,
                    $this->typeMap,
                );
                $this->typeMap[$className] = $ifaceType;
                $this->typeMap[$ifaceType->name()] = $ifaceType;
                $interfaces[$className] = $ifaceType;
            } catch (\ReflectionException) {
                continue;
            }
        }

        return $interfaces;
    }

    /**
     * Build ObjectType definitions for all discovered type classes.
     *
     * @param array<class-string>                                   $typeClasses Discovered type class names
     * @param array<string, \GraphQL\Type\Definition\InterfaceType> $interfaces  Built interfaces
     *
     * @return array<string, ObjectType>
     */
    private function buildObjectTypes(array $typeClasses, array $interfaces): array
    {
        /** @var array<string, ObjectType> $objectTypes */
        $objectTypes = [];

        foreach ($typeClasses as $className) {
            try {
                $reflection = new ReflectionClass($className);
                $attrs = $reflection->getAttributes(TypeAttr::class);
                if ($attrs === []) {
                    continue;
                }
                $attr = $attrs[0]->newInstance();
                $objType = $this->typeBuilder->buildObjectType(
                    $className,
                    $reflection,
                    $attr,
                    $this->typeMap,
                    $interfaces,
                );
                $this->typeMap[$className] = $objType;
                $this->typeMap[$objType->name()] = $objType;
                $objectTypes[$className] = $objType;
            } catch (\ReflectionException) {
                continue;
            }
        }

        return $objectTypes;
    }

    /**
     * Build UnionType definitions for all discovered union classes.
     *
     * @param array<class-string>       $unionClasses Discovered union class names
     * @param array<string, ObjectType> $objectTypes  Built object types
     *
     * @return void
     */
    private function buildUnionTypes(array $unionClasses, array $objectTypes): void
    {
        foreach ($unionClasses as $className) {
            try {
                $reflection = new ReflectionClass($className);
                $attrs = $reflection->getAttributes(UnionTypeAttr::class);
                if ($attrs === []) {
                    continue;
                }
                $attr = $attrs[0]->newInstance();
                $unionType = $this->typeBuilder->buildUnionType(
                    $className,
                    $reflection,
                    $attr,
                    $objectTypes,
                );
                $this->typeMap[$className] = $unionType;
                $this->typeMap[$unionType->name()] = $unionType;
            } catch (\ReflectionException) {
                continue;
            }
        }
    }

    /**
     * Build a root type (Query or Mutation) by collecting fields from annotated classes.
     *
     * @param string              $rootName    Root type name ('Query' or 'Mutation')
     * @param array<class-string> $classes     Discovered query/mutation class names
     * @param class-string        $attrClass   The attribute class to look for
     *
     * @return ObjectType
     */
    private function buildRootType(string $rootName, array $classes, string $attrClass): ObjectType
    {
        $fields = [];

        foreach ($classes as $className) {
            try {
                $reflection = new ReflectionClass($className);
                /** @var array<\ReflectionAttribute<Query|Mutation>> $attrs */
                $attrs = $reflection->getAttributes($attrClass);
                if ($attrs === []) {
                    continue;
                }

                /** @var Query|Mutation $attr */
                $attr = $attrs[0]->newInstance();
                $fieldName = $attr->name;

                $invokeMethod = $reflection->hasMethod('__invoke')
                    ? $reflection->getMethod('__invoke')
                    : null;

                if ($invokeMethod === null) {
                    continue;
                }

                // Determine field return type
                $returnType = $attr->type !== null
                    ? $this->argumentBuilder->parseTypeString($attr->type, $this->typeMap)
                    : $this->resolveMethodReturnType($invokeMethod);

                // Build arguments from __invoke parameters
                $args = $this->argumentBuilder->buildFromMethod($invokeMethod, 0, $this->typeMap);

                // Collect middleware
                $middlewareList = $this->collectMiddleware($reflection, $invokeMethod);

                $container = $this->container;

                $fields[$fieldName] = [
                    'type'        => $returnType,
                    'description' => $attr->description,
                    'args'        => $args ?: null,
                    'resolve'     => static function (mixed $root, array $args, GraphQLContext $context) use (
                        $className,
                        $container,
                        $invokeMethod,
                        $middlewareList,
                    ): mixed {
                        $instance = $container->get($className);

                        // Build call arguments
                        $callArgs = [];
                        foreach ($invokeMethod->getParameters() as $param) {
                            $callArgs[] = self::resolveInvokeParameter($param, $args, $context, $container);
                        }

                        if ($middlewareList !== []) {
                            return self::runMiddlewareChain(
                                $middlewareList,
                                $root,
                                $args,
                                $context,
                                static fn() => $instance(...$callArgs),
                            );
                        }

                        return $instance(...$callArgs);
                    },
                ];

                // Remove null entries
                $fields[$fieldName] = array_filter($fields[$fieldName], static fn($v) => $v !== null);
            } catch (\ReflectionException) {
                continue;
            }
        }

        if ($fields === []) {
            // GraphQL requires at least one field
            $fields['_empty'] = [
                'type' => Type::boolean(),
                'resolve' => static fn(): bool => true,
            ];
        }

        return new ObjectType([
            'name'   => $rootName,
            'fields' => $fields,
        ]);
    }

    /**
     * Build the Subscription root type from annotated classes.
     *
     * @param array<class-string> $subscriptionClasses Discovered subscription classes
     *
     * @return ObjectType
     */
    private function buildSubscriptionType(array $subscriptionClasses): ObjectType
    {
        $fields = [];

        foreach ($subscriptionClasses as $className) {
            try {
                $reflection = new ReflectionClass($className);
                $attrs = $reflection->getAttributes(Subscription::class);
                if ($attrs === []) {
                    continue;
                }

                /** @var Subscription $attr */
                $attr = $attrs[0]->newInstance();
                $fieldName = $attr->name;

                $subscribeMethod = $reflection->hasMethod('subscribe')
                    ? $reflection->getMethod('subscribe')
                    : null;
                $resolveMethod = $reflection->hasMethod('resolve')
                    ? $reflection->getMethod('resolve')
                    : null;

                if ($subscribeMethod === null) {
                    continue;
                }

                $returnType = $resolveMethod !== null
                    ? $this->resolveMethodReturnType($resolveMethod)
                    : Type::string();

                $container = $this->container;

                $fields[$fieldName] = [
                    'type'        => $returnType,
                    'description' => $attr->description,
                    'subscribe'   => static function (mixed $root, array $args, GraphQLContext $context) use ($className, $container): mixed {
                        $instance = $container->get($className);
                        return $instance->subscribe($root, $args, $context);
                    },
                    'resolve'     => static function (mixed $payload, array $args, GraphQLContext $context) use ($className, $container): mixed {
                        $instance = $container->get($className);
                        if (method_exists($instance, 'resolve')) {
                            return $instance->resolve($payload, $args, $context);
                        }
                        return $payload;
                    },
                ];
            } catch (\ReflectionException) {
                continue;
            }
        }

        if ($fields === []) {
            $fields['_empty'] = [
                'type' => Type::boolean(),
                'resolve' => static fn(): bool => true,
            ];
        }

        return new ObjectType([
            'name'   => 'Subscription',
            'fields' => $fields,
        ]);
    }

    /**
     * Resolve a method's return type to a GraphQL type.
     *
     * @param \ReflectionMethod $method The method to inspect
     *
     * @return \GraphQL\Type\Definition\Type
     */
    private function resolveMethodReturnType(\ReflectionMethod $method): \GraphQL\Type\Definition\Type
    {
        $returnType = $method->getReturnType();
        if (!$returnType instanceof \ReflectionNamedType) {
            return Type::string();
        }

        $typeName = $returnType->getName();
        $baseType = $this->argumentBuilder->phpTypeToGraphQL($typeName, $this->typeMap) ?? Type::string();

        return $returnType->allowsNull() ? $baseType : Type::nonNull($baseType);
    }

    /**
     * Resolve an __invoke parameter to its runtime value.
     *
     * @param \ReflectionParameter  $param     The parameter
     * @param array<string, mixed>  $args      GraphQL arguments
     * @param GraphQLContext        $context   Execution context
     * @param ContainerInterface    $container DI container
     *
     * @return mixed
     */
    private static function resolveInvokeParameter(
        \ReflectionParameter $param,
        array $args,
        GraphQLContext $context,
        ContainerInterface $container,
    ): mixed {
        $type = $param->getType();
        $paramName = $param->getName();

        // Check GraphQLContext
        if ($type instanceof \ReflectionNamedType && $type->getName() === GraphQLContext::class) {
            return $context;
        }

        // Check #[Arg] attribute
        $argAttrs = $param->getAttributes(Arg::class);
        $argName = $paramName;
        if ($argAttrs !== []) {
            $instance = $argAttrs[0]->newInstance();
            $argName = $instance->name ?? $paramName;
        }

        if (array_key_exists($argName, $args)) {
            return $args[$argName];
        }

        // Try DI container
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if ($container->has($typeName)) {
                return $container->get($typeName);
            }
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        return null;
    }

    /**
     * Collect middleware from both class-level and method-level attributes.
     *
     * @param ReflectionClass<object>  $classRef   The class reflection
     * @param \ReflectionMethod        $method     The method reflection
     *
     * @return array<string>
     */
    private function collectMiddleware(ReflectionClass $classRef, \ReflectionMethod $method): array
    {
        $list = [];

        // Class-level middleware
        foreach ($classRef->getAttributes(Middleware::class) as $attr) {
            $mw = $attr->newInstance();
            $list = array_merge($list, $mw->middleware);
        }

        // Method-level middleware
        foreach ($method->getAttributes(Middleware::class) as $attr) {
            $mw = $attr->newInstance();
            $list = array_merge($list, $mw->middleware);
        }

        return $list;
    }

    /**
     * Run a middleware chain around a resolver.
     *
     * @param array<string>        $middlewareClasses Middleware class names
     * @param mixed                $root              Parent object
     * @param array<string, mixed> $args              Arguments
     * @param GraphQLContext       $context           Context
     * @param \Closure             $resolver          Final resolver
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
}
