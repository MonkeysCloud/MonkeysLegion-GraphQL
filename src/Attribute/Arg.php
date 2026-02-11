<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Provides metadata for a GraphQL argument on a resolver method parameter.
 *
 * Applied to parameters of __invoke() on Query/Mutation classes or to
 * parameters of #[Field] methods on Type classes.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Arg
{
    /** @var string Sentinel value indicating no default was provided. */
    public const UNDEFINED = '__UNDEFINED__';

    /**
     * @param string|null $name         Argument name (defaults to parameter name)
     * @param string|null $type         GraphQL type string override
     * @param string|null $description  Human-readable description
     * @param bool        $nullable     Whether the argument is nullable
     * @param mixed       $defaultValue Default value (use UNDEFINED sentinel for none)
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?string $description = null,
        public readonly bool $nullable = false,
        public readonly mixed $defaultValue = self::UNDEFINED,
    ) {}

    /**
     * Check whether a default value was explicitly provided.
     *
     * @return bool
     */
    public function hasDefaultValue(): bool
    {
        return $this->defaultValue !== self::UNDEFINED;
    }
}
