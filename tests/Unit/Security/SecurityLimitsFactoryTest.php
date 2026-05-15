<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Security\SecurityLimitsFactory;
use MonkeysLegion\GraphQL\Security\DepthLimiter;
use MonkeysLegion\GraphQL\Security\ComplexityAnalyzer;
use MonkeysLegion\GraphQL\Security\IntrospectionControl;

class SecurityLimitsFactoryTest extends TestCase
{
    public function testEmptyConfigReturnsNoRules(): void
    {
        $rules = SecurityLimitsFactory::fromConfig([]);
        $this->assertCount(0, $rules);
    }

    public function testDepthLimitConfigured(): void
    {
        $rules = SecurityLimitsFactory::fromConfig(['maxDepth' => 10]);
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(DepthLimiter::class, $rules[0]);
    }

    public function testComplexityConfigured(): void
    {
        $rules = SecurityLimitsFactory::fromConfig(['maxComplexity' => 500]);
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(ComplexityAnalyzer::class, $rules[0]);
    }

    public function testIntrospectionDisabled(): void
    {
        $rules = SecurityLimitsFactory::fromConfig(['introspection' => false]);
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(IntrospectionControl::class, $rules[0]);
    }

    public function testFullConfig(): void
    {
        $rules = SecurityLimitsFactory::fromConfig([
            'maxDepth' => 10,
            'maxComplexity' => 500,
            'fieldCosts' => ['users' => 5],
            'defaultCost' => 2,
            'introspection' => false,
        ]);

        $this->assertCount(3, $rules);
        $this->assertInstanceOf(DepthLimiter::class, $rules[0]);
        $this->assertInstanceOf(ComplexityAnalyzer::class, $rules[1]);
        $this->assertInstanceOf(IntrospectionControl::class, $rules[2]);
    }
}
