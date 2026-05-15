<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Declares a policy gate for field-level or mutation-level authorization.
 * The policy class is resolved from the DI container and its `authorize()`
 * method is called with the user object and the resolver arguments.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Policy
{
    /**
     * @param class-string $policyClass The policy class to resolve
     * @param string $method The method to call on the policy (default: 'authorize')
     */
    public function __construct(
        public readonly string $policyClass,
        public readonly string $method = 'authorize',
    ) {}
}
