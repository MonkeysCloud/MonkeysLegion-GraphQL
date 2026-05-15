<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Resolver;

use MonkeysLegion\GraphQL\Resolver\FilterResolver;
use PHPUnit\Framework\TestCase;

final class FilterResolverTest extends TestCase
{
    public function testExtractCriteriaEmpty(): void
    {
        [$criteria, $orderBy] = FilterResolver::extractCriteria([]);
        $this->assertSame([], $criteria);
        $this->assertSame([], $orderBy);
    }

    public function testExtractCriteriaWithWhere(): void
    {
        [$criteria, $orderBy] = FilterResolver::extractCriteria([
            'where' => ['status' => 'active', 'category' => 'tech'],
        ]);
        $this->assertSame(['status' => 'active', 'category' => 'tech'], $criteria);
        $this->assertSame([], $orderBy);
    }

    public function testExtractCriteriaWithOrderByAsc(): void
    {
        [$criteria, $orderBy] = FilterResolver::extractCriteria([
            'orderBy' => ['name', '+price'],
        ]);
        $this->assertSame([], $criteria);
        $this->assertSame(['name' => 'asc', 'price' => 'asc'], $orderBy);
    }

    public function testExtractCriteriaWithOrderByDesc(): void
    {
        [$criteria, $orderBy] = FilterResolver::extractCriteria([
            'orderBy' => ['-created_at', '-updated_at'],
        ]);
        $this->assertSame(['created_at' => 'desc', 'updated_at' => 'desc'], $orderBy);
    }

    public function testExtractCriteriaWithBoth(): void
    {
        [$criteria, $orderBy] = FilterResolver::extractCriteria([
            'where'   => ['active' => true],
            'orderBy' => ['-id'],
        ]);
        $this->assertSame(['active' => true], $criteria);
        $this->assertSame(['id' => 'desc'], $orderBy);
    }

    public function testExtractCriteriaIgnoresNonArrayWhere(): void
    {
        [$criteria, $orderBy] = FilterResolver::extractCriteria([
            'where' => 'not-an-array',
        ]);
        $this->assertSame([], $criteria);
    }
}
