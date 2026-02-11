<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Relay-style PageInfo type.
 *
 * Contains pagination metadata for connections.
 */
final class PageInfoType
{
    /** @var ObjectType|null Singleton instance */
    private static ?ObjectType $instance = null;

    /**
     * Create or retrieve the PageInfo type.
     *
     * @return ObjectType
     */
    public static function create(): ObjectType
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new ObjectType([
            'name'   => 'PageInfo',
            'fields' => [
                'hasNextPage' => [
                    'type'        => Type::nonNull(Type::boolean()),
                    'description' => 'When paginating forwards, are there more items?',
                ],
                'hasPreviousPage' => [
                    'type'        => Type::nonNull(Type::boolean()),
                    'description' => 'When paginating backwards, are there more items?',
                ],
                'startCursor' => [
                    'type'        => Type::string(),
                    'description' => 'When paginating backwards, the cursor to continue.',
                ],
                'endCursor' => [
                    'type'        => Type::string(),
                    'description' => 'When paginating forwards, the cursor to continue.',
                ],
            ],
            'description' => 'Information about pagination in a connection.',
        ]);

        return self::$instance;
    }

    /**
     * Clear the singleton cache.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$instance = null;
    }
}
