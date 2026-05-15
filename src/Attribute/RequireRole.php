<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Requires the user to have specific role(s) before executing a resolver.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequireRole
{
    /** @var list<string> */
    public readonly array $roles;

    /**
     * @param string|list<string> $roles Required role(s)
     * @param 'all'|'any' $mode Whether ALL or ANY roles must match
     */
    public function __construct(
        string|array $roles,
        public readonly string $mode = 'any',
    ) {
        $this->roles = is_array($roles) ? $roles : [$roles];
    }
}
