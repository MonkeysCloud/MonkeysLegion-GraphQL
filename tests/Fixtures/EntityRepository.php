<?php
declare(strict_types=1);

namespace MonkeysLegion\Query\Repository;

abstract class EntityRepository
{
    public function findByIds(array $ids): array
    {
        return [];
    }

    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return [];
    }

    public function find(int|string $id): ?object
    {
        return null;
    }

    public function findAll(array $orderBy = []): array
    {
        return [];
    }
}

interface UninitializedProxy {}
