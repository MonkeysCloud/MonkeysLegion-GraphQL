<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Scanner;

use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\GraphQL\Attribute\GraphQLResource;
use ReflectionClass;
use ReflectionException;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Scans for classes annotated with #[GraphQLResource] and #[Entity].
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ResourceScanner
{
    /**
     * Scan directories for GraphQL resources.
     *
     * @param list<string> $directories Absolute paths to scan
     *
     * @return list<class-string> Found class names with #[GraphQLResource]
     */
    public function scan(array $directories): array
    {
        $resources = [];

        foreach ($directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                if ($content === false || !str_contains($content, 'GraphQLResource')) {
                    continue;
                }

                $className = $this->extractClassName($content);
                if ($className === null) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass($className);
                    if ($reflection->getAttributes(GraphQLResource::class) !== []) {
                        if ($reflection->getAttributes(Entity::class) !== []) {
                            $resources[] = $className;
                        }
                    }
                } catch (ReflectionException) {
                    continue;
                }
            }
        }

        return $resources;
    }

    private function extractClassName(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/m', $content, $namespaceMatches) !== 1) {
            return null;
        }

        if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $classMatches) !== 1) {
            return null;
        }

        $namespace = trim($namespaceMatches[1]);
        $class = trim($classMatches[1]);

        return $namespace . '\\' . $class;
    }
}
