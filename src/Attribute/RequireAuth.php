<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Requires the user to be authenticated before executing a resolver.
 * Equivalent to the auth package's #[Authenticated] but scoped to
 * GraphQL queries, mutations, and individual fields.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequireAuth
{
    /**
     * @param string|null $guard Optionally require a specific auth guard (e.g. 'api', 'jwt')
     */
    public function __construct(
        public readonly ?string $guard = null,
    ) {}
}
