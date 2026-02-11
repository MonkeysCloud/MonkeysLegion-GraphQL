<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Loader;

use MonkeysLegion\GraphQL\Loader\DataLoader;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use PHPUnit\Framework\TestCase;

final class DataLoaderTest extends TestCase
{
    public function testLoadAndFlush(): void
    {
        $loader = new class extends DataLoader {
            public int $batchCallCount = 0;

            protected function batchLoad(array $keys): array
            {
                $this->batchCallCount++;
                return array_map(static fn(int $key) => "result-{$key}", $keys);
            }
        };

        // Queue keys — load() returns null for uncached keys
        $result1 = $loader->load(1);
        $result2 = $loader->load(2);
        $result3 = $loader->load(1); // Duplicate — queued but still not loaded

        self::assertNull($result1);
        self::assertNull($result2);
        self::assertNull($result3);

        // Flush triggers batch
        $loader->flush();

        // Now values are cached
        self::assertSame('result-1', $loader->load(1));
        self::assertSame('result-2', $loader->load(2));
        self::assertSame(1, $loader->batchCallCount);
    }

    public function testCachePriming(): void
    {
        $loader = new class extends DataLoader {
            public int $batchCallCount = 0;

            protected function batchLoad(array $keys): array
            {
                $this->batchCallCount++;
                return array_map(static fn(int $key) => "loaded-{$key}", $keys);
            }
        };

        $loader->prime(1, 'pre-loaded');
        $result = $loader->load(1); // Should return cached value immediately
        self::assertSame('pre-loaded', $result);
        self::assertSame(0, $loader->batchCallCount); // Should NOT have called batch
    }

    public function testClearRemovesCachedValue(): void
    {
        $loader = new class extends DataLoader {
            public int $batchCallCount = 0;

            protected function batchLoad(array $keys): array
            {
                $this->batchCallCount++;
                return array_map(fn(int $key) => "v{$this->batchCallCount}-{$key}", $keys);
            }
        };

        $loader->load(1);
        $loader->flush();
        self::assertSame('v1-1', $loader->load(1));

        $loader->clear(1);
        $loader->load(1); // re-queue after clearing
        $loader->flush();
        self::assertSame('v2-1', $loader->load(1));
    }

    public function testRegistryDirectRegister(): void
    {
        $registry = new DataLoaderRegistry();
        $loader = new class extends DataLoader {
            protected function batchLoad(array $keys): array
            {
                return $keys;
            }
        };

        $registry->register('users', $loader);
        $retrieved = $registry->get('users');
        self::assertSame($loader, $retrieved);
    }

    public function testRegistryFactoryRegister(): void
    {
        $registry = new DataLoaderRegistry();
        $loader = new class extends DataLoader {
            protected function batchLoad(array $keys): array
            {
                return $keys;
            }
        };

        $registry->registerFactory('users', static fn() => $loader);
        $retrieved = $registry->get('users');
        self::assertSame($loader, $retrieved);
    }

    public function testRegistryFlushAll(): void
    {
        $registry = new DataLoaderRegistry();
        $flushed = false;

        $loader = new class($flushed) extends DataLoader {
            public function __construct(private bool &$flushed) {}

            protected function batchLoad(array $keys): array
            {
                $this->flushed = true;
                return $keys;
            }
        };

        $registry->register('test', $loader);
        $loader->load(1);
        $registry->flushAll();

        self::assertTrue($flushed);
    }

    public function testLoadDeferred(): void
    {
        $loader = new class extends DataLoader {
            protected function batchLoad(array $keys): array
            {
                return array_map(static fn(int $key) => "val-{$key}", $keys);
            }
        };

        $received = null;
        $loader->loadDeferred(1, static function (mixed $value) use (&$received): void {
            $received = $value;
        });

        self::assertNull($received);
        $loader->flush();
        self::assertSame('val-1', $received);
    }
}
