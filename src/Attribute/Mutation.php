<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Marks a class as a GraphQL root Mutation field.
 *
 * The class must have an __invoke() method. Parameters on __invoke become
 * GraphQL arguments (use #[Arg] for metadata). Constructor receives DI services.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Mutation
{
    /**
     * @param string      $name        Mutation field name
     * @param string|null $description Human-readable description
     * @param string|null $type        Return type override (GraphQL type string)
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $type = null,
    ) {}
}