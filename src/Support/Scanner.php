<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use MonkeysLegion\GraphQL\Attribute as A;

final class Scanner
{
    /**
     * Scans a directory for GraphQL types, queries, mutations, and subscriptions.
     *
     * @return array{types:class-string[],queries:class-string[],mutations:class-string[],subscriptions:class-string[]}
     * @throws \ReflectionException
     */
    public function scan(string $baseDir, string $baseNs): array
    {
        $types = $queries = $mutations = $subscriptions = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $rel  = substr($file->getPathname(), strlen($baseDir) + 1, -4);
            $fqcn = $baseNs . '\\' . str_replace('/', '\\', $rel);

            if (! class_exists($fqcn)) {
                require_once $file->getPathname();
            }

            $ref = new ReflectionClass($fqcn);

            if ($ref->getAttributes(A\Type::class)) {
                $types[] = $fqcn;
            }
            if ($ref->getAttributes(A\Query::class)) {
                $queries[] = $fqcn;
            }
            if ($ref->getAttributes(A\Mutation::class)) {
                $mutations[] = $fqcn;
            }
            if ($ref->getAttributes(A\Subscription::class)) {
                $subscriptions[] = $fqcn;
            }
        }

        return compact('types', 'queries', 'mutations', 'subscriptions');
    }
}