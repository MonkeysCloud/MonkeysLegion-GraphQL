<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Schema;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use MonkeysLegion\GraphQL\Support\Scanner;
use Psr\Container\ContainerInterface;

/**
 * SchemaFactory is responsible for building the GraphQL schema by scanning
 * the specified directory for GraphQL types, queries, and mutations.
 */
final class SchemaFactory
{

/**
     * Create a new SchemaFactory instance.
     *
     * @param ContainerInterface $container The service container to resolve dependencies.
     * @param Scanner            $scanner   The scanner to find GraphQL types, queries, and mutations.
     * @param string             $dir       The directory to scan for GraphQL definitions.
     * @param string             $ns        The namespace for the GraphQL definitions.
     */
    public function __construct(
        private ContainerInterface $container,
        private Scanner            $scanner,
        private string             $dir   = 'app/GraphQL',
        private string             $ns    = 'App\\GraphQL',
    ) {}

    /**
     * Build the GraphQL schema by scanning the specified directory.
     *
     * @return Schema The constructed GraphQL schema.
     */
    public function build(): Schema
    {
        $found = $this->scanner->scan(base_path($this->dir), $this->ns);

        $typeMap = [];
        foreach ($found['types'] as $class) {
            $typeMap[$class] = $this->container->get($class);
        }

        $queryFields = [];
        foreach ($found['queries'] as $class) {
            /** @var ObjectType $obj */
            $obj = $this->container->get($class);
            $queryFields += $obj->getFields();
        }

        $mutationFields = [];
        foreach ($found['mutations'] as $class) {
            /** @var ObjectType $obj */
            $obj = $this->container->get($class);
            $mutationFields += $obj->getFields();
        }

        return new Schema([
            'query'    => new ObjectType(['name' => 'Query',    'fields' => $queryFields ?: fn() => ['_'=> Type::boolean()]]),
            'mutation' => $mutationFields ? new ObjectType(['name'=>'Mutation','fields'=>$mutationFields]) : null,
            'typeLoader' => fn(string $name) => $typeMap[$name] ?? null,
        ]);
    }
}