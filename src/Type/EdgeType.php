<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Relay-style Edge type factory.
 *
 * Creates edge types containing a node and cursor.
 */
final class EdgeType
{
    /** @var array<string, ObjectType> Cache of created edge types */
    private static array $cache = [];

    /**
     * Create or retrieve an Edge type for a given node type.
     *
     * @param string     $name     The name prefix (e.g., "User" → "UserEdge")
     * @param ObjectType $nodeType The node type
     *
     * @return ObjectType
     */
    public static function create(string $name, ObjectType $nodeType): ObjectType
    {
        $key = $name . 'Edge';

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $edgeType = new ObjectType([
            'name'   => $key,
            'fields' => [
                'node' => [
                    'type'        => Type::nonNull($nodeType),
                    'description' => 'The item at the end of the edge.',
                ],
                'cursor' => [
                    'type'        => Type::nonNull(Type::string()),
                    'description' => 'A cursor for use in pagination.',
                ],
            ],
            'description' => "An edge in a {$name} connection.",
        ]);

        self::$cache[$key] = $edgeType;
        return $edgeType;
    }

    /**
     * Clear the type cache.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
