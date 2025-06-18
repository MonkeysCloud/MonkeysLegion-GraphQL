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
 * the specified directory for GraphQL types, queries, mutations, and subscriptions.
 */
final class SchemaFactory
{
    /**
     * @param ContainerInterface $container The service container to resolve dependencies.
     * @param Scanner            $scanner   The scanner to find GraphQL definitions.
     * @param string             $dir       Directory to scan for GraphQL classes.
     * @param string             $ns        Namespace for those classes.
     */
    public function __construct(
        private ContainerInterface $container,
        private Scanner            $scanner,
        private string             $dir   = 'app/GraphQL',
        private string             $ns    = 'App\\GraphQL',
    ) {}

    /**
     * Build the GraphQL schema, including Query, Mutation, and Subscription root types.
     */
    public function build(): Schema
    {
        $found = $this->scanner->scan(base_path($this->dir), $this->ns);

        // 1) Build a map of all named types
        $typeMap = [];
        foreach ($found['types'] as $class) {
            $typeMap[$class] = $this->container->get($class);
        }

        // 2) Collect Query fields
        $queryFields = [];
        foreach ($found['queries'] as $class) {
            /** @var ObjectType $obj */
            $obj = $this->container->get($class);
            $queryFields += $obj->getFields();
        }

        // 3) Collect Mutation fields
        $mutationFields = [];
        foreach ($found['mutations'] as $class) {
            /** @var ObjectType $obj */
            $obj = $this->container->get($class);
            $mutationFields += $obj->getFields();
        }

        // 4) **Collect Subscription fields** (new)
        $subscriptionFields = [];
        foreach ($found['subscriptions'] as $class) {
            /** @var ObjectType $obj */
            $obj = $this->container->get($class);
            $subscriptionFields += $obj->getFields();
        }

        // 5) Construct the Schema with optional subscription root
        return new Schema([
            'query'        => new ObjectType([
                'name'   => 'Query',
                'fields' => $queryFields ?: fn() => ['_'=> Type::boolean()],
            ]),
            'mutation'     => $mutationFields
                ? new ObjectType([
                    'name'   => 'Mutation',
                    'fields' => $mutationFields,
                ])
                : null,
            'subscription' => $subscriptionFields
                ? new ObjectType([
                    'name'   => 'Subscription',
                    'fields' => $subscriptionFields,
                ])
                : null,
            'typeLoader'   => fn(string $name) => $typeMap[$name] ?? null,
        ]);
    }
}