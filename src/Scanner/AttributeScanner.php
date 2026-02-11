<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Scanner;

use MonkeysLegion\GraphQL\Attribute\Enum;
use MonkeysLegion\GraphQL\Attribute\InputType;
use MonkeysLegion\GraphQL\Attribute\InterfaceType;
use MonkeysLegion\GraphQL\Attribute\Mutation;
use MonkeysLegion\GraphQL\Attribute\Query;
use MonkeysLegion\GraphQL\Attribute\Subscription;
use MonkeysLegion\GraphQL\Attribute\Type;
use MonkeysLegion\GraphQL\Attribute\UnionType;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Recursively scans configured directories for PHP classes with GraphQL attributes.
 *
 * Returns categorized collections of discovered class names, each keyed by
 * attribute kind (types, queries, mutations, subscriptions, inputs, enums,
 * interfaces, unions).
 *
 * @phpstan-type ScanResult array{
 *     types: array<class-string>,
 *     queries: array<class-string>,
 *     mutations: array<class-string>,
 *     subscriptions: array<class-string>,
 *     inputs: array<class-string>,
 *     enums: array<class-string>,
 *     interfaces: array<class-string>,
 *     unions: array<class-string>,
 * }
 */
final class AttributeScanner
{
    /**
     * Mapping from attribute FQCN to result category key.
     *
     * @var array<class-string, string>
     */
    private const ATTRIBUTE_MAP = [
        Type::class          => 'types',
        Query::class         => 'queries',
        Mutation::class      => 'mutations',
        Subscription::class  => 'subscriptions',
        InputType::class     => 'inputs',
        Enum::class          => 'enums',
        InterfaceType::class => 'interfaces',
        UnionType::class     => 'unions',
    ];

    /**
     * Scan the given directories for PHP files containing GraphQL attributes.
     *
     * @param array<string> $directories Absolute paths to scan
     *
     * @return array{
     *     types: array<class-string>,
     *     queries: array<class-string>,
     *     mutations: array<class-string>,
     *     subscriptions: array<class-string>,
     *     inputs: array<class-string>,
     *     enums: array<class-string>,
     *     interfaces: array<class-string>,
     *     unions: array<class-string>,
     * }
     */
    public function scan(array $directories): array
    {
        /** @var array<string, array<class-string>> $result */
        $result = [
            'types'         => [],
            'queries'       => [],
            'mutations'     => [],
            'subscriptions' => [],
            'inputs'        => [],
            'enums'         => [],
            'interfaces'    => [],
            'unions'        => [],
        ];

        $classNames = $this->discoverClassNames($directories);

        foreach ($classNames as $className) {
            if (!class_exists($className) && !interface_exists($className) && !enum_exists($className)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);
            } catch (\ReflectionException) {
                continue;
            }

            foreach (self::ATTRIBUTE_MAP as $attributeClass => $category) {
                $attributes = $reflection->getAttributes($attributeClass);
                if ($attributes !== []) {
                    $result[$category][] = $className;
                    break; // A class can only have one primary GraphQL attribute
                }
            }
        }

        return $result;
    }

    /**
     * Scan a single class and return its attribute instance if it has a GraphQL attribute.
     *
     * @param class-string $className Fully-qualified class name
     *
     * @return array{category: string, attribute: object, reflection: ReflectionClass<object>}|null
     */
    public function scanClass(string $className): ?array
    {
        try {
            $reflection = new ReflectionClass($className);
        } catch (\ReflectionException) {
            return null;
        }

        foreach (self::ATTRIBUTE_MAP as $attributeClass => $category) {
            $attributes = $reflection->getAttributes($attributeClass);
            if ($attributes !== []) {
                return [
                    'category'   => $category,
                    'attribute'  => $attributes[0]->newInstance(),
                    'reflection' => $reflection,
                ];
            }
        }

        return null;
    }

    /**
     * Discover fully-qualified class names from PHP files in the given directories.
     *
     * @param array<string> $directories Absolute paths to scan
     *
     * @return array<class-string>
     */
    private function discoverClassNames(array $directories): array
    {
        $classNames = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->extractClassName($file->getRealPath());
                if ($className !== null) {
                    $classNames[] = $className;
                }
            }
        }

        return $classNames;
    }

    /**
     * Extract the fully-qualified class/enum/interface name from a PHP file.
     *
     * Parses the file tokens to find the namespace and class/enum/interface declaration.
     *
     * @param string $filePath Absolute path to the PHP file
     *
     * @return class-string|null
     */
    private function extractClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $className = null;
        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            // Find namespace declaration
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = '';
                $i++;
                while ($i < $count) {
                    $t = $tokens[$i];
                    if (is_array($t) && in_array($t[0], [T_NAME_QUALIFIED, T_STRING], true)) {
                        $namespace .= $t[1];
                    } elseif (is_string($t) && ($t === ';' || $t === '{')) {
                        break;
                    }
                    $i++;
                }
            }

            // Find class, interface, or enum declaration
            if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_ENUM], true)) {
                // Skip anonymous classes
                $prevIndex = $i - 1;
                while ($prevIndex >= 0 && is_array($tokens[$prevIndex]) && $tokens[$prevIndex][0] === T_WHITESPACE) {
                    $prevIndex--;
                }
                if ($prevIndex >= 0 && is_string($tokens[$prevIndex]) && $tokens[$prevIndex] === '(') {
                    $i++;
                    continue;
                }

                $i++;
                while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $className = $tokens[$i][1];
                    break;
                }
            }

            $i++;
        }

        if ($className === null) {
            return null;
        }

        $fqcn = $namespace !== '' ? $namespace . '\\' . $className : $className;

        /** @var class-string */
        return $fqcn;
    }
}
