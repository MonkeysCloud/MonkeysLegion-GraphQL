<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Resolver;

use MonkeysLegion\GraphQL\Resolver\FieldResolver;
use PHPUnit\Framework\TestCase;

final class FieldResolverTest extends TestCase
{
    public function testResolveFromArray(): void
    {
        $this->assertSame('bar', FieldResolver::resolveValue(['foo' => 'bar'], 'foo'));
        $this->assertNull(FieldResolver::resolveValue(['foo' => 'bar'], 'missing'));
    }

    public function testResolveFromNull(): void
    {
        $this->assertNull(FieldResolver::resolveValue(null, 'anything'));
    }

    public function testResolveFromScalar(): void
    {
        $this->assertNull(FieldResolver::resolveValue(42, 'anything'));
    }

    public function testResolveFromObjectProperty(): void
    {
        $obj = new class { public string $name = 'Alice'; };
        $this->assertSame('Alice', FieldResolver::resolveValue($obj, 'name'));
    }

    public function testResolveFromGetter(): void
    {
        $obj = new class {
            public function getTitle(): string { return 'Hello'; }
        };
        $this->assertSame('Hello', FieldResolver::resolveValue($obj, 'title'));
    }

    public function testResolveFromMethodSameName(): void
    {
        $obj = new class {
            public function status(): string { return 'active'; }
        };
        $this->assertSame('active', FieldResolver::resolveValue($obj, 'status'));
    }

    public function testResolveFromMagicGet(): void
    {
        $obj = new class {
            private array $data = ['magic' => 'value'];
            public function __get(string $name): mixed { return $this->data[$name] ?? null; }
        };
        $this->assertSame('value', FieldResolver::resolveValue($obj, 'magic'));
    }

    public function testResolveReturnsNullWhenNoMatch(): void
    {
        $obj = new class {};
        $this->assertNull(FieldResolver::resolveValue($obj, 'nonexistent'));
    }
}
