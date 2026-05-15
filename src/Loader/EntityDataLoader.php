<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Loader;

use GraphQL\Deferred;
use MonkeysLegion\Query\Repository\EntityRepository;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Batches and executes database queries to solve N+1 problems natively.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class EntityDataLoader
{
    /** @var array<string, array<int|string, \Closure>> */
    private array $primaryKeyBuffer = [];

    /** @var array<string, array<string, array<int|string, \Closure>>> */
    private array $foreignKeyBuffer = [];

    /** @var array<string, array<int|string, object>> */
    private array $primaryKeyCache = [];

    /** @var array<string, array<string, array<int|string, list<object>>>> */
    private array $foreignKeyCache = [];

    /**
     * Load a single entity by its primary key.
     *
     * @param EntityRepository $repository
     * @param int|string       $id
     *
     * @return Deferred
     */
    public function loadById(EntityRepository $repository, int|string $id): Deferred
    {
        $repoClass = $repository::class;

        return new Deferred(function () use ($repository, $repoClass, $id) {
            if (isset($this->primaryKeyCache[$repoClass][$id])) {
                return $this->primaryKeyCache[$repoClass][$id];
            }

            if (!isset($this->primaryKeyBuffer[$repoClass])) {
                $this->primaryKeyBuffer[$repoClass] = [];
            }

            // We use an internal Deferred inside the main Deferred to suspend execution
            // until all IDs at this level are collected.
            return new Deferred(function () use ($repository, $repoClass, $id) {
                if (!isset($this->primaryKeyCache[$repoClass][$id])) {
                    $this->flushPrimaryKey($repository, $repoClass);
                }
                return $this->primaryKeyCache[$repoClass][$id] ?? null;
            });
        });
    }

    /**
     * Internal method to queue an ID for fetching.
     */
    public function queueById(EntityRepository $repository, int|string $id): Deferred
    {
        $repoClass = $repository::class;

        if (isset($this->primaryKeyCache[$repoClass][$id])) {
            return new Deferred(fn() => $this->primaryKeyCache[$repoClass][$id]);
        }

        $promise = new \stdClass(); // Placeholder object reference

        $this->primaryKeyBuffer[$repoClass][$id] = static function ($entity) use ($promise) {
            $promise->entity = $entity;
        };

        return new Deferred(function () use ($repository, $repoClass, $id, $promise) {
            if (isset($this->primaryKeyBuffer[$repoClass])) {
                $this->flushPrimaryKey($repository, $repoClass);
            }
            return $promise->entity ?? null;
        });
    }

    /**
     * Load a collection of entities by a foreign key (e.g., ManyToOne inverse or OneToMany).
     *
     * @param EntityRepository $repository
     * @param string           $foreignKeyColumn
     * @param int|string       $id
     *
     * @return Deferred
     */
    public function loadByForeignKey(EntityRepository $repository, string $foreignKeyColumn, int|string $id): Deferred
    {
        $repoClass = $repository::class;

        if (isset($this->foreignKeyCache[$repoClass][$foreignKeyColumn][$id])) {
            return new Deferred(fn() => $this->foreignKeyCache[$repoClass][$foreignKeyColumn][$id]);
        }

        $promise = new \stdClass();

        $this->foreignKeyBuffer[$repoClass][$foreignKeyColumn][$id] = static function ($entities) use ($promise) {
            $promise->entities = $entities;
        };

        return new Deferred(function () use ($repository, $repoClass, $foreignKeyColumn, $id, $promise) {
            if (isset($this->foreignKeyBuffer[$repoClass][$foreignKeyColumn])) {
                $this->flushForeignKey($repository, $repoClass, $foreignKeyColumn);
            }
            return $promise->entities ?? [];
        });
    }

    private function flushPrimaryKey(EntityRepository $repository, string $repoClass): void
    {
        if (!isset($this->primaryKeyBuffer[$repoClass]) || $this->primaryKeyBuffer[$repoClass] === []) {
            return;
        }

        $buffer = $this->primaryKeyBuffer[$repoClass];
        $this->primaryKeyBuffer[$repoClass] = []; // Clear before execution

        $ids = array_keys($buffer);
        $entities = $repository->findByIds($ids);

        // Assume entities have public id property for demo, or extract properly.
        // A robust implementation would use a hydrator or reflection.
        foreach ($entities as $entity) {
            if (property_exists($entity, 'id')) {
                $id = $entity->id;
                $this->primaryKeyCache[$repoClass][$id] = $entity;
            } else {
                $reflection = new \ReflectionClass($entity);
                if ($reflection->hasProperty('id')) {
                    $id = $reflection->getProperty('id')->getValue($entity);
                    $this->primaryKeyCache[$repoClass][$id] = $entity;
                }
            }
        }

        foreach ($buffer as $id => $callback) {
            $callback($this->primaryKeyCache[$repoClass][$id] ?? null);
        }
    }

    private function flushForeignKey(EntityRepository $repository, string $repoClass, string $foreignKeyColumn): void
    {
        if (!isset($this->foreignKeyBuffer[$repoClass][$foreignKeyColumn]) || $this->foreignKeyBuffer[$repoClass][$foreignKeyColumn] === []) {
            return;
        }

        $buffer = $this->foreignKeyBuffer[$repoClass][$foreignKeyColumn];
        $this->foreignKeyBuffer[$repoClass][$foreignKeyColumn] = [];

        $ids = array_keys($buffer);
        // Find all where foreign key IN (ids)
        $entities = $repository->findBy([$foreignKeyColumn => $ids]);

        $grouped = [];
        foreach ($entities as $entity) {
            $reflection = new \ReflectionClass($entity);
            if ($reflection->hasProperty($foreignKeyColumn)) {
                $fkValue = $reflection->getProperty($foreignKeyColumn)->getValue($entity);
                $grouped[$fkValue][] = $entity;
            }
        }

        foreach ($buffer as $id => $callback) {
            $list = $grouped[$id] ?? [];
            $this->foreignKeyCache[$repoClass][$foreignKeyColumn][$id] = $list;
            $callback($list);
        }
    }
}
