<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Attaches per-field or per-class middleware to a GraphQL resolver.
 *
 * Repeatable: multiple middleware can be stacked on the same target.
 * Middleware is resolved via the DI container and executed in declaration order.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Middleware
{
    /** @var array<string> Middleware class names to apply. */
    public readonly array $middleware;

    /**
     * @param string|array<string> $middleware Middleware class name(s)
     */
    public function __construct(string|array $middleware)
    {
        $this->middleware = is_string($middleware) ? [$middleware] : $middleware;
    }
}
