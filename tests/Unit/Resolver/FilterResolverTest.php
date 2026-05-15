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

    public function testOperatorGt(): void
    {
        [$criteria] = FilterResolver::extractCriteria([
            'where' => ['price_gt' => 100],
        ]);
        $this->assertSame(['price' => ['>', 100]], $criteria);
    }

    public function testOperatorLte(): void
    {
        [$criteria] = FilterResolver::extractCriteria([
            'where' => ['age_lte' => 30],
        ]);
        $this->assertSame(['age' => ['<=', 30]], $criteria);
    }

    public function testOperatorIn(): void
    {
        [$criteria] = FilterResolver::extractCriteria([
            'where' => ['status_in' => ['active', 'pending']],
        ]);
        $this->assertSame(['status' => ['IN', ['active', 'pending']]], $criteria);
    }

    public function testOperatorNotIn(): void
    {
        [$criteria] = FilterResolver::extractCriteria([
            'where' => ['role_not_in' => ['banned']],
        ]);
        $this->assertSame(['role' => ['NOT IN', ['banned']]], $criteria);
    }

    public function testOperatorLike(): void
    {
        [$criteria] = FilterResolver::extractCriteria([
            'where' => ['name_like' => '%john%'],
        ]);
        $this->assertSame(['name' => ['LIKE', '%john%']], $criteria);
    }

    public function testOperatorNot(): void
    {
        [$criteria] = FilterResolver::extractCriteria([
            'where' => ['status_not' => 'deleted'],
        ]);
        $this->assertSame(['status' => ['!=', 'deleted']], $criteria);
    }

    public function testMixedOperatorsAndEquality(): void
    {
        [$criteria] = FilterResolver::extractCriteria([
            'where' => [
                'category' => 'tech',
                'price_gt' => 50,
                'stock_lt' => 100,
                'tags_in'  => ['php', 'graphql'],
            ],
        ]);

        $this->assertSame('tech', $criteria['category']);
        $this->assertSame(['>', 50], $criteria['price']);
        $this->assertSame(['<', 100], $criteria['stock']);
        $this->assertSame(['IN', ['php', 'graphql']], $criteria['tags']);
    }
}
