<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Loader;

use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use MonkeysLegion\GraphQL\Loader\DataLoader;
use PHPUnit\Framework\TestCase;

final class DataLoaderRegistryTest extends TestCase
{
    public function testRegisterAndGet(): void
    {
        $registry = new DataLoaderRegistry();
        $loader = $this->createMock(DataLoader::class);

        $registry->register('users', $loader);
        $this->assertSame($loader, $registry->get('users'));
    }

    public function testHas(): void
    {
        $registry = new DataLoaderRegistry();
        $this->assertFalse($registry->has('users'));

        $registry->register('users', $this->createMock(DataLoader::class));
        $this->assertTrue($registry->has('users'));
    }

    public function testRegisterFactory(): void
    {
        $registry = new DataLoaderRegistry();
        $loader = $this->createMock(DataLoader::class);

        $called = false;
        $registry->registerFactory('users', function () use ($loader, &$called) {
            $called = true;
            return $loader;
        });

        $this->assertTrue($registry->has('users'));
        $this->assertFalse($called);

        $result = $registry->get('users');
        $this->assertTrue($called);
        $this->assertSame($loader, $result);

        // Second call should return cached instance
        $result2 = $registry->get('users');
        $this->assertSame($loader, $result2);
    }

    public function testGetThrowsForUnregistered(): void
    {
        $registry = new DataLoaderRegistry();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DataLoader "missing" not found');
        $registry->get('missing');
    }

    public function testNames(): void
    {
        $registry = new DataLoaderRegistry();
        $registry->register('users', $this->createMock(DataLoader::class));
        $registry->registerFactory('posts', fn() => $this->createMock(DataLoader::class));

        $names = $registry->names();
        $this->assertContains('users', $names);
        $this->assertContains('posts', $names);
    }
}
