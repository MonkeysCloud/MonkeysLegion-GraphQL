<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Cache;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use MonkeysLegion\GraphQL\Cache\SchemaCache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class SchemaCacheTest extends TestCase
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
                'fields' => [
                    'hello' => ['type' => Type::string(), 'resolve' => fn() => 'world'],
                ],
            ]),
        ]);
    }

    public function testGetOrBuildCachesAndRetrieves(): void
    {
        $schemaCache = new SchemaCache($this->cache);
        $buildCount = 0;

        $builder = function () use (&$buildCount) {
            $buildCount++;
            return $this->makeSchema();
        };

        $schema1 = $schemaCache->getOrBuild('test', $builder);
        $this->assertInstanceOf(Schema::class, $schema1);
        $this->assertSame(1, $buildCount);

        // Second call should use cache
        $schema2 = $schemaCache->getOrBuild('test', $builder);
        $this->assertInstanceOf(Schema::class, $schema2);
        $this->assertSame(1, $buildCount); // Should not rebuild
    }

    public function testPutAndHas(): void
    {
        $schemaCache = new SchemaCache($this->cache);
        $this->assertFalse($schemaCache->has('my-schema'));

        $schemaCache->put('my-schema', $this->makeSchema());
        $this->assertTrue($schemaCache->has('my-schema'));
    }

    public function testClearRemovesCachedSchema(): void
    {
        $schemaCache = new SchemaCache($this->cache);
        $schemaCache->put('my-schema', $this->makeSchema());
        $this->assertTrue($schemaCache->has('my-schema'));

        $schemaCache->clear('my-schema');
        $this->assertFalse($schemaCache->has('my-schema'));
    }
}
