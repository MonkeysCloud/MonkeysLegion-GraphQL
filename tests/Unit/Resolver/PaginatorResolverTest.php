<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Resolver;

use MonkeysLegion\GraphQL\Resolver\PaginatorResolver;
use PHPUnit\Framework\TestCase;

final class PaginatorResolverTest extends TestCase
{
    public function testCursorEncodeDecode(): void
    {
        // Use reflection to test private methods
        $encode = new \ReflectionMethod(PaginatorResolver::class, 'encodeCursor');
        $decode = new \ReflectionMethod(PaginatorResolver::class, 'decodeCursor');

        $encoded = $encode->invoke(null, '42');
        $this->assertIsString($encoded);

        $decoded = $decode->invoke(null, $encoded);
        $this->assertSame('42', $decoded);
    }

    public function testDecodeCursorReturnsNullOnInvalid(): void
    {
        $decode = new \ReflectionMethod(PaginatorResolver::class, 'decodeCursor');

        $this->assertNull($decode->invoke(null, 'not-base64'));
        $this->assertNull($decode->invoke(null, base64_encode('invalid:42')));
    }

    public function testDecodeCursorWithValidPrefix(): void
    {
        $decode = new \ReflectionMethod(PaginatorResolver::class, 'decodeCursor');

        $valid = base64_encode('cursor:100');
        $this->assertSame('100', $decode->invoke(null, $valid));
    }
}
