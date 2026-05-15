<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Requires the user to have specific permission(s) before executing a resolver.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequirePermission
{
    /** @var list<string> */
    public readonly array $permissions;

    /**
     * @param string|list<string> $permissions Required permission(s)
     * @param 'all'|'any' $mode Whether ALL or ANY permissions must match
     */
    public function __construct(
        string|array $permissions,
        public readonly string $mode = 'all',
    ) {
        $this->permissions = is_array($permissions) ? $permissions : [$permissions];
    }
}
