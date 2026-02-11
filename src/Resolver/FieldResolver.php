<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Resolver;

use MonkeysLegion\GraphQL\Context\GraphQLContext;

/**
 * Default field resolver that delegates to the parent object.
 *
 * Handles property access, getter methods, and array key lookups.
 * Used as the default resolver when no explicit resolve function is provided.
 */
final class FieldResolver
{
    /**
     * Resolve a field value from the parent/root object.
     *
     * Resolution order:
     * 1. Array key access
     * 2. Public property access
     * 3. Getter method (getFieldName or fieldName)
     * 4. __get magic method
     * 5. null
     *
     * @param mixed                $root      The parent object or array
     * @param array<string, mixed> $args      GraphQL arguments
     * @param GraphQLContext       $context   Execution context
     * @param \GraphQL\Type\Definition\ResolveInfo $info Resolve info
     *
     * @return mixed
     */
    public function __invoke(
        mixed $root,
        array $args,
        GraphQLContext $context,
        \GraphQL\Type\Definition\ResolveInfo $info,
    ): mixed {
        $fieldName = $info->fieldName;
        return self::resolveValue($root, $fieldName);
    }

    /**
     * Resolve a value from a root object by field name.
     *
     * @param mixed  $root      The parent object or array
     * @param string $fieldName The field name to resolve
     *
     * @return mixed
     */
    public static function resolveValue(mixed $root, string $fieldName): mixed
    {
        if ($root === null) {
            return null;
        }

        // Array access
        if (is_array($root)) {
            return $root[$fieldName] ?? null;
        }

        if (!is_object($root)) {
            return null;
        }

        // Public property
        if (property_exists($root, $fieldName)) {
            return $root->$fieldName;
        }

        // Getter method: getFieldName()
        $getter = 'get' . ucfirst($fieldName);
        if (method_exists($root, $getter)) {
            return $root->$getter();
        }

        // Method with same name: fieldName()
        if (method_exists($root, $fieldName)) {
            return $root->$fieldName();
        }

        // __get magic
        if (method_exists($root, '__get')) {
            return $root->$fieldName;
        }

        return null;
    }
}
