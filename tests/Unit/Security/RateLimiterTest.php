<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Security;

use MonkeysLegion\GraphQL\Security\RateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class RateLimiterTest extends TestCase
{
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->cache = new class implements CacheInterface {
            private array $store = [];
            public function get(string $key, mixed $default = null): mixed { return $this->store[$key] ?? $default; }
            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool { $this->store[$key] = $value; return true; }
            public function delete(string $key): bool { unset($this->store[$key]); return true; }
            public function clear(): bool { $this->store = []; return true; }
            public function has(string $key): bool { return isset($this->store[$key]); }
            public function getMultiple(iterable $keys, mixed $default = null): iterable { return []; }
            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool { return true; }
            public function deleteMultiple(iterable $keys): bool { return true; }
        };
    }

    public function testIsAllowedWithinLimit(): void
    {
        $limiter = new RateLimiter($this->cache, maxRequests: 3, windowSeconds: 60);

        $this->assertTrue($limiter->isAllowed('client-1'));
        $this->assertTrue($limiter->isAllowed('client-1'));
        $this->assertTrue($limiter->isAllowed('client-1'));
    }

    public function testIsAllowedExceedsLimit(): void
    {
        $limiter = new RateLimiter($this->cache, maxRequests: 2, windowSeconds: 60);

        $this->assertTrue($limiter->isAllowed('client-1'));
        $this->assertTrue($limiter->isAllowed('client-1'));
        $this->assertFalse($limiter->isAllowed('client-1'));
    }

    public function testRemainingCount(): void
    {
        $limiter = new RateLimiter($this->cache, maxRequests: 5, windowSeconds: 60);

        $this->assertSame(5, $limiter->remaining('client-1'));
        $limiter->isAllowed('client-1');
        $this->assertSame(4, $limiter->remaining('client-1'));
    }

    public function testReset(): void
    {
        $limiter = new RateLimiter($this->cache, maxRequests: 1, windowSeconds: 60);

        $limiter->isAllowed('client-1');
        $this->assertFalse($limiter->isAllowed('client-1'));

        $limiter->reset('client-1');
        $this->assertTrue($limiter->isAllowed('client-1'));
    }

    public function testRetryAfter(): void
    {
        $limiter = new RateLimiter($this->cache, maxRequests: 1, windowSeconds: 60);

        // Before any request
        $this->assertSame(0, $limiter->retryAfter('client-1'));

        $limiter->isAllowed('client-1');
        $retry = $limiter->retryAfter('client-1');
        $this->assertGreaterThan(0, $retry);
        $this->assertLessThanOrEqual(60, $retry);
    }

    public function testDifferentClientsAreIndependent(): void
    {
        $limiter = new RateLimiter($this->cache, maxRequests: 1, windowSeconds: 60);

        $this->assertTrue($limiter->isAllowed('client-a'));
        $this->assertFalse($limiter->isAllowed('client-a'));
        $this->assertTrue($limiter->isAllowed('client-b'));
    }
}
