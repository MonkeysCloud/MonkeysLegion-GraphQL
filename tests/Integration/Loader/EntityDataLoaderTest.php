<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Integration\Loader;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Loader\EntityDataLoader;
use GraphQL\Deferred;

require_once __DIR__ . '/../../Fixtures/EntityRepository.php';

use MonkeysLegion\Query\Repository\EntityRepository;

class EntityDataLoaderTest extends TestCase
{
    public function testQueueByIdReturnsDeferred(): void
    {
        $repository = $this->createMock(EntityRepository::class);

        $loader = new EntityDataLoader();

        $deferred1 = $loader->queueById($repository, 1);
        $deferred2 = $loader->queueById($repository, 2);

        // queueById returns Deferred instances that will batch on resolution
        $this->assertInstanceOf(Deferred::class, $deferred1);
        $this->assertInstanceOf(Deferred::class, $deferred2);
    }

    public function testLoadByForeignKeyReturnsDeferred(): void
    {
        $repository = $this->createMock(EntityRepository::class);

        $loader = new EntityDataLoader();

        $deferred = $loader->loadByForeignKey($repository, 'post_id', 1);

        // loadByForeignKey returns a Deferred that will batch on resolution
        $this->assertInstanceOf(Deferred::class, $deferred);
    }
}
