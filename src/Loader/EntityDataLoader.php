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
        // Delegate to queueById so the ID is always queued into the buffer
        return $this->queueById($repository, $id);
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

        foreach ($entities as $entity) {
            $id = self::extractProperty($entity, 'id');
            if ($id !== null) {
                $this->primaryKeyCache[$repoClass][$id] = $entity;
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
            $fkValue = self::extractProperty($entity, $foreignKeyColumn);
            if ($fkValue !== null) {
                $grouped[$fkValue][] = $entity;
            }
        }

        foreach ($buffer as $id => $callback) {
            $list = $grouped[$id] ?? [];
            $this->foreignKeyCache[$repoClass][$foreignKeyColumn][$id] = $list;
            $callback($list);
        }
    }

    /**
     * Extract a property value from an entity, handling non-public visibility.
     *
     * @param object $entity   The entity object
     * @param string $property The property name to extract
     *
     * @return mixed The property value, or null if not found
     */
    private static function extractProperty(object $entity, string $property): mixed
    {
        // Handle dynamic/public properties (e.g. stdClass from (object) cast)
        if (property_exists($entity, $property)) {
            $reflection = new \ReflectionClass($entity);

            // If the class has a declared property, use reflection for safety
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);

                if (!$prop->isPublic()) {
                    $prop->setAccessible(true);
                }

                return $prop->getValue($entity);
            }

            // Dynamic property (e.g. stdClass) — safe to access directly
            return $entity->{$property};
        }

        return null;
    }
}
