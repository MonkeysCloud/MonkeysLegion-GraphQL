<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Relay-style Connection type factory.
 *
 * Creates the connection wrapper type containing edges and pageInfo.
 */
final class ConnectionType
{
    /** @var array<string, ObjectType> Cache of created connection types */
    private static array $cache = [];

    /**
     * Create or retrieve a Connection type for a given node type.
     *
     * @param string     $name     The name prefix (e.g., "User" → "UserConnection")
     * @param ObjectType $nodeType The node type for edges
     *
     * @return ObjectType
     */
    public static function create(string $name, ObjectType $nodeType): ObjectType
    {
        $key = $name . 'Connection';

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $edgeType = EdgeType::create($name, $nodeType);
        $pageInfoType = PageInfoType::create();

        $connectionType = new ObjectType([
            'name'   => $key,
            'fields' => static fn() => [
                'edges' => [
                    'type'        => Type::nonNull(Type::listOf(Type::nonNull($edgeType))),
                    'description' => 'A list of edges.',
                ],
                'pageInfo' => [
                    'type'        => Type::nonNull($pageInfoType),
                    'description' => 'Information to aid in pagination.',
                ],
                'totalCount' => [
                    'type'        => Type::int(),
                    'description' => 'Total number of items in the connection.',
                ],
            ],
            'description' => "A connection to a list of {$name} items.",
        ]);

        self::$cache[$key] = $connectionType;
        return $connectionType;
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
