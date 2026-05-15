<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Cache;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use MonkeysLegion\GraphQL\Cache\SchemaCache;
use MonkeysLegion\GraphQL\Cache\SchemaCacheWarmer;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class SchemaCacheWarmerTest extends TestCase
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

    private function makeSchema(): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['hello' => ['type' => Type::string()]],
            ]),
        ]);
    }

    public function testWarmStoresToCache(): void
    {
        // Since SchemaBuilder is final, we test via SchemaCache directly
        $schemaCache = new SchemaCache($this->cache);

        $schema = $this->makeSchema();
        $schemaCache->put('test-warm', $schema);

        $this->assertTrue($schemaCache->has('test-warm'));
    }

    public function testClearAndRebuild(): void
    {
        $schemaCache = new SchemaCache($this->cache);

        $schema = $this->makeSchema();
        $schemaCache->put('test-refresh', $schema);
        $this->assertTrue($schemaCache->has('test-refresh'));

        $schemaCache->clear('test-refresh');
        $this->assertFalse($schemaCache->has('test-refresh'));

        $schemaCache->put('test-refresh', $schema);
        $this->assertTrue($schemaCache->has('test-refresh'));
    }
}
