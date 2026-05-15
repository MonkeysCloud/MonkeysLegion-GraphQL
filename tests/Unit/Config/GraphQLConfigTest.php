<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Config;

use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use PHPUnit\Framework\TestCase;

final class GraphQLConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new GraphQLConfig();

        $this->assertSame('/graphql', $config->endpoint());
        $this->assertSame('/graphiql', $config->graphiqlEndpoint());
        $this->assertTrue($config->graphiqlEnabled());
        $this->assertSame(['app/GraphQL'], $config->scanDirectories());
        $this->assertSame('App\\GraphQL', $config->scanNamespace());
        $this->assertSame(10, $config->maxDepth());
        $this->assertSame(1000, $config->maxComplexity());
        $this->assertTrue($config->introspectionEnabled());
        $this->assertFalse($config->persistedQueriesEnabled());
        $this->assertSame(100, $config->rateLimitMaxRequests());
        $this->assertSame(60, $config->rateLimitWindowSeconds());
        $this->assertFalse($config->schemaCacheEnabled());
        $this->assertSame(3600, $config->schemaCacheTtl());
        $this->assertFalse($config->subscriptionsEnabled());
        $this->assertSame('memory', $config->subscriptionDriver());
        $this->assertSame('0.0.0.0', $config->subscriptionHost());
        $this->assertSame(6001, $config->subscriptionPort());
        $this->assertSame('redis://127.0.0.1:6379', $config->redisDsn());
        $this->assertFalse($config->debugMode());
    }

    public function testCustomValues(): void
    {
        $config = new GraphQLConfig([
            'graphql.endpoint'                        => '/api/graphql',
            'graphql.graphiql_endpoint'               => '/api/graphiql',
            'graphql.graphiql_enabled'                => false,
            'graphql.scan_dirs'                       => ['src/GraphQL', 'src/Types'],
            'graphql.scan_namespace'                  => 'MyApp\\GraphQL',
            'graphql.security.max_depth'              => 5,
            'graphql.security.max_complexity'         => 500,
            'graphql.security.introspection'          => false,
            'graphql.security.persisted_queries'      => true,
            'graphql.security.rate_limit.max_requests'   => 50,
            'graphql.security.rate_limit.window_seconds' => 30,
            'graphql.cache.enabled'                   => true,
            'graphql.cache.ttl'                       => 7200,
            'graphql.subscriptions.enabled'           => true,
            'graphql.subscriptions.driver'            => 'redis',
            'graphql.subscriptions.host'              => '127.0.0.1',
            'graphql.subscriptions.port'              => 8080,
            'graphql.subscriptions.redis_dsn'         => 'redis://redis:6379',
            'graphql.debug'                           => true,
        ]);

        $this->assertSame('/api/graphql', $config->endpoint());
        $this->assertSame('/api/graphiql', $config->graphiqlEndpoint());
        $this->assertFalse($config->graphiqlEnabled());
        $this->assertSame(['src/GraphQL', 'src/Types'], $config->scanDirectories());
        $this->assertSame('MyApp\\GraphQL', $config->scanNamespace());
        $this->assertSame(5, $config->maxDepth());
        $this->assertSame(500, $config->maxComplexity());
        $this->assertFalse($config->introspectionEnabled());
        $this->assertTrue($config->persistedQueriesEnabled());
        $this->assertSame(50, $config->rateLimitMaxRequests());
        $this->assertSame(30, $config->rateLimitWindowSeconds());
        $this->assertTrue($config->schemaCacheEnabled());
        $this->assertSame(7200, $config->schemaCacheTtl());
        $this->assertTrue($config->subscriptionsEnabled());
        $this->assertSame('redis', $config->subscriptionDriver());
        $this->assertSame('127.0.0.1', $config->subscriptionHost());
        $this->assertSame(8080, $config->subscriptionPort());
        $this->assertSame('redis://redis:6379', $config->redisDsn());
        $this->assertTrue($config->debugMode());
    }

    public function testGetRaw(): void
    {
        $config = new GraphQLConfig(['my.custom.key' => 'hello']);
        $this->assertSame('hello', $config->get('my.custom.key'));
        $this->assertNull($config->get('missing'));
        $this->assertSame('default', $config->get('missing', 'default'));
    }

    public function testScanDirStringCoercedToArray(): void
    {
        $config = new GraphQLConfig(['graphql.scan_dirs' => 'single/dir']);
        $this->assertSame(['single/dir'], $config->scanDirectories());
    }
}
