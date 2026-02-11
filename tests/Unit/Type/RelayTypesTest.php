<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use MonkeysLegion\GraphQL\Type\ConnectionType;
use MonkeysLegion\GraphQL\Type\EdgeType;
use MonkeysLegion\GraphQL\Type\PageInfoType;
use PHPUnit\Framework\TestCase;

final class RelayTypesTest extends TestCase
{
    protected function setUp(): void
    {
        ConnectionType::clearCache();
        EdgeType::clearCache();
        PageInfoType::clearCache();
    }

    public function testPageInfoType(): void
    {
        $type = PageInfoType::create();
        self::assertSame('PageInfo', $type->name);

        $fields = $type->getFieldNames();
        self::assertContains('hasNextPage', $fields);
        self::assertContains('hasPreviousPage', $fields);
        self::assertContains('startCursor', $fields);
        self::assertContains('endCursor', $fields);
    }

    public function testPageInfoIsSingleton(): void
    {
        $a = PageInfoType::create();
        $b = PageInfoType::create();
        self::assertSame($a, $b);
    }

    public function testEdgeType(): void
    {
        $nodeType = new ObjectType([
            'name'   => 'Item',
            'fields' => ['id' => ['type' => Type::int()]],
        ]);

        $edge = EdgeType::create('Item', $nodeType);
        self::assertSame('ItemEdge', $edge->name);

        $fields = $edge->getFieldNames();
        self::assertContains('node', $fields);
        self::assertContains('cursor', $fields);
    }

    public function testEdgeTypeIsCached(): void
    {
        $nodeType = new ObjectType([
            'name'   => 'Widget',
            'fields' => ['id' => ['type' => Type::int()]],
        ]);

        $a = EdgeType::create('Widget', $nodeType);
        $b = EdgeType::create('Widget', $nodeType);
        self::assertSame($a, $b);
    }

    public function testConnectionType(): void
    {
        $nodeType = new ObjectType([
            'name'   => 'Gadget',
            'fields' => ['id' => ['type' => Type::int()]],
        ]);

        $conn = ConnectionType::create('Gadget', $nodeType);
        self::assertSame('GadgetConnection', $conn->name);

        $fields = $conn->getFieldNames();
        self::assertContains('edges', $fields);
        self::assertContains('pageInfo', $fields);
        self::assertContains('totalCount', $fields);
    }

    public function testConnectionTypeIsCached(): void
    {
        $nodeType = new ObjectType([
            'name'   => 'Todo',
            'fields' => ['id' => ['type' => Type::int()]],
        ]);

        $a = ConnectionType::create('Todo', $nodeType);
        $b = ConnectionType::create('Todo', $nodeType);
        self::assertSame($a, $b);
    }
}
