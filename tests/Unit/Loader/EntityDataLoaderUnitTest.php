<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Loader;

use MonkeysLegion\GraphQL\Loader\EntityDataLoader;
use GraphQL\Deferred;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Fixtures/EntityRepository.php';

use MonkeysLegion\Query\Repository\EntityRepository;

final class EntityDataLoaderUnitTest extends TestCase
{
    public function testQueueByIdReturnsDeferredForNewIds(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $loader = new EntityDataLoader();

        $d1 = $loader->queueById($repository, 1);
        $d2 = $loader->queueById($repository, 2);

        $this->assertInstanceOf(Deferred::class, $d1);
        $this->assertInstanceOf(Deferred::class, $d2);
    }

    public function testLoadByForeignKeyReturnsDeferredForNewIds(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $loader = new EntityDataLoader();

        $d = $loader->loadByForeignKey($repository, 'user_id', 1);
        $this->assertInstanceOf(Deferred::class, $d);
    }

    public function testLoadByIdReturnsDeferredForNewIds(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $loader = new EntityDataLoader();

        $d = $loader->loadById($repository, 42);
        $this->assertInstanceOf(Deferred::class, $d);
    }
}
