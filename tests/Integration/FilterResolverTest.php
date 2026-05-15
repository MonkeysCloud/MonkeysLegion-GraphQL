<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Resolver\FilterResolver;

class FilterResolverTest extends TestCase
{
    public function testExtractCriteria(): void
    {
        $args = [
            'where' => [
                'status' => 'ACTIVE',
                'price_gt' => 100,
            ],
            'orderBy' => ['+created_at', '-price'],
        ];

        [$criteria, $orderBy] = FilterResolver::extractCriteria($args);

        // Assert criteria
        $this->assertArrayHasKey('status', $criteria);
        $this->assertSame('ACTIVE', $criteria['status']);
        $this->assertArrayHasKey('price_gt', $criteria);
        $this->assertSame(100, $criteria['price_gt']);

        // Assert orderBy
        $this->assertCount(2, $orderBy);
        $this->assertArrayHasKey('created_at', $orderBy);
        $this->assertSame('asc', $orderBy['created_at']);
        $this->assertArrayHasKey('price', $orderBy);
        $this->assertSame('desc', $orderBy['price']);
    }
}
