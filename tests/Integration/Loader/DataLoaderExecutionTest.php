<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Integration\Loader;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Loader\EntityDataLoader;
use GraphQL\Deferred;
use MonkeysLegion\Query\Repository\EntityRepository;

require_once __DIR__ . '/../../Fixtures/EntityRepository.php';

class DataLoaderExecutionTest extends TestCase
{
    /**
     * Tests that EntityDataLoader batches multiple queueById() calls into
     * a single findByIds() repository call, and resolves all queued items.
     */
    public function testDataLoaderBatchesQueuedIds(): void
    {
        $findByIdsCalls = 0;

        $repository = new class($findByIdsCalls) extends EntityRepository {
            public function __construct(private int &$calls) {}

            public function findByIds(array $ids): array
            {
                $this->calls++;
                $data = [
                    1 => (object) ['id' => 1, 'name' => 'John'],
                    2 => (object) ['id' => 2, 'name' => 'Jane'],
                    3 => (object) ['id' => 3, 'name' => 'Bob'],
                ];
                return array_values(array_intersect_key($data, array_flip($ids)));
            }
        };

        $loader = new EntityDataLoader();

        // Queue 3 IDs via queueById
        $d1 = $loader->queueById($repository, 1);
        $d2 = $loader->queueById($repository, 2);
        $d3 = $loader->queueById($repository, 3);

        $this->assertInstanceOf(Deferred::class, $d1);
        $this->assertInstanceOf(Deferred::class, $d2);
        $this->assertInstanceOf(Deferred::class, $d3);

        // Trigger resolution — Deferred::runQueue() flushes all pending
        Deferred::runQueue();

        // findByIds should have been called exactly ONCE (batched!)
        $this->assertSame(1, $findByIdsCalls, 'findByIds should be called exactly once for all queued IDs');
    }

    /**
     * Tests that loadById (which now delegates to queueById) also batches.
     */
    public function testLoadByIdDelegatesToQueueById(): void
    {
        $findByIdsCalls = 0;

        $repository = new class($findByIdsCalls) extends EntityRepository {
            public function __construct(private int &$calls) {}

            public function findByIds(array $ids): array
            {
                $this->calls++;
                return array_map(fn($id) => (object) ['id' => $id, 'name' => "User$id"], $ids);
            }
        };

        $loader = new EntityDataLoader();

        // loadById should delegate to queueById
        $d1 = $loader->loadById($repository, 10);
        $d2 = $loader->loadById($repository, 20);

        $this->assertInstanceOf(Deferred::class, $d1);
        $this->assertInstanceOf(Deferred::class, $d2);

        Deferred::runQueue();

        $this->assertSame(1, $findByIdsCalls, 'loadById should batch via queueById');
    }

    /**
     * Tests that loadByForeignKey batches multiple FK lookups.
     */
    public function testLoadByForeignKeyBatches(): void
    {
        $findByCalls = 0;

        $repository = new class($findByCalls) extends EntityRepository {
            public function __construct(private int &$calls) {}

            public function findBy(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
            {
                $this->calls++;
                // Simulate results for author_id IN ($criteria values)
                return [
                    (object) ['id' => 10, 'author_id' => 1, 'title' => 'Post A'],
                    (object) ['id' => 20, 'author_id' => 1, 'title' => 'Post B'],
                    (object) ['id' => 30, 'author_id' => 2, 'title' => 'Post C'],
                ];
            }
        };

        $loader = new EntityDataLoader();

        $d1 = $loader->loadByForeignKey($repository, 'author_id', 1);
        $d2 = $loader->loadByForeignKey($repository, 'author_id', 2);

        $this->assertInstanceOf(Deferred::class, $d1);
        $this->assertInstanceOf(Deferred::class, $d2);

        Deferred::runQueue();

        $this->assertSame(1, $findByCalls, 'findBy should be called exactly once for all queued FK IDs');
    }

    /**
     * Tests that queueById with cached ID returns immediately without re-fetching.
     */
    public function testCachedIdReturnsWithoutRefetch(): void
    {
        $findByIdsCalls = 0;

        $repository = new class($findByIdsCalls) extends EntityRepository {
            public function __construct(private int &$calls) {}

            public function findByIds(array $ids): array
            {
                $this->calls++;
                return array_map(fn($id) => (object) ['id' => $id, 'name' => "User$id"], $ids);
            }
        };

        $loader = new EntityDataLoader();

        // First batch
        $loader->queueById($repository, 1);
        Deferred::runQueue();
        $this->assertSame(1, $findByIdsCalls);

        // Second call for same ID — should use cache
        $loader->queueById($repository, 1);
        Deferred::runQueue();
        $this->assertSame(1, $findByIdsCalls, 'Should not re-fetch cached ID');
    }
}
