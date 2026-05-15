<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Security;

use MonkeysLegion\GraphQL\Security\PersistedQueries;
use MonkeysLegion\GraphQL\Security\PersistedQueryNotFoundError;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class PersistedQueriesTest extends TestCase
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

    public function testProcessWithoutPersistedQueryReturnsQuery(): void
    {
        $pq = new PersistedQueries($this->cache);
        $result = $pq->process('{ users { id } }', []);
        $this->assertSame('{ users { id } }', $result);
    }

    public function testProcessThrowsWhenNoQueryAndNoExtension(): void
    {
        $pq = new PersistedQueries($this->cache);
        $this->expectException(\RuntimeException::class);
        $pq->process(null, []);
    }

    public function testProcessStoresAndReturnsByHash(): void
    {
        $pq = new PersistedQueries($this->cache);
        $query = '{ users { id name } }';
        $hash = hash('sha256', $query);

        // First: send both query and hash — should store
        $result = $pq->process($query, [
            'persistedQuery' => ['sha256Hash' => $hash, 'version' => 1],
        ]);
        $this->assertSame($query, $result);

        // Second: send only hash — should retrieve
        $result = $pq->process(null, [
            'persistedQuery' => ['sha256Hash' => $hash, 'version' => 1],
        ]);
        $this->assertSame($query, $result);
    }

    public function testProcessThrowsNotFoundWhenHashNotCached(): void
    {
        $pq = new PersistedQueries($this->cache);
        $this->expectException(PersistedQueryNotFoundError::class);
        $pq->process(null, [
            'persistedQuery' => ['sha256Hash' => 'deadbeef', 'version' => 1],
        ]);
    }

    public function testProcessThrowsOnHashMismatch(): void
    {
        $pq = new PersistedQueries($this->cache);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('hash mismatch');
        $pq->process('{ posts { id } }', [
            'persistedQuery' => ['sha256Hash' => 'wronghash', 'version' => 1],
        ]);
    }

    public function testRegisterAndClear(): void
    {
        $pq = new PersistedQueries($this->cache);
        $query = '{ test }';
        $hash = hash('sha256', $query);

        $pq->register($hash, $query);

        $result = $pq->process(null, [
            'persistedQuery' => ['sha256Hash' => $hash, 'version' => 1],
        ]);
        $this->assertSame($query, $result);

        $pq->clear($hash);

        $this->expectException(PersistedQueryNotFoundError::class);
        $pq->process(null, [
            'persistedQuery' => ['sha256Hash' => $hash, 'version' => 1],
        ]);
    }

    public function testPersistedQueryNotFoundErrorFormat(): void
    {
        $error = new PersistedQueryNotFoundError();
        $gql = $error->toGraphQLError();
        $this->assertSame('PersistedQueryNotFound', $gql['message']);
        $this->assertSame('PERSISTED_QUERY_NOT_FOUND', $gql['extensions']['code']);
    }
}
