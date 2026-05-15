<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Security;

use GraphQL\Error\Error;
use MonkeysLegion\GraphQL\Attribute\Policy;
use MonkeysLegion\GraphQL\Attribute\RequireAuth;
use MonkeysLegion\GraphQL\Attribute\RequirePermission;
use MonkeysLegion\GraphQL\Attribute\RequireRole;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Enforces authentication, role-based access control, permission checks,
 * and policy gates on GraphQL resolvers. Security attributes are read
 * from resolver classes and methods and enforced at resolution time.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class SecurityEnforcer
{
    /**
     * Wrap a resolver callable with security checks.
     *
     * Scans the given class/method for #[RequireAuth], #[RequireRole],
     * #[RequirePermission], and #[Policy] attributes, then returns a
     * closure that enforces them before delegating to the original resolver.
     *
     * @param callable               $resolver     The original resolver
     * @param ReflectionClass|null   $class        The resolver class (for class-level attrs)
     * @param ReflectionMethod|null  $method       The resolver method (for method-level attrs)
     *
     * @return callable The security-wrapped resolver
     */
    public static function wrap(callable $resolver, ?ReflectionClass $class = null, ?ReflectionMethod $method = null): callable
    {
        $authChecks = [];
        $roleChecks = [];
        $permChecks = [];
        $policyChecks = [];

        // Collect from class
        if ($class !== null) {
            foreach ($class->getAttributes(RequireAuth::class) as $attr) {
                $authChecks[] = $attr->newInstance();
            }
            foreach ($class->getAttributes(RequireRole::class) as $attr) {
                $roleChecks[] = $attr->newInstance();
            }
            foreach ($class->getAttributes(RequirePermission::class) as $attr) {
                $permChecks[] = $attr->newInstance();
            }
            foreach ($class->getAttributes(Policy::class) as $attr) {
                $policyChecks[] = $attr->newInstance();
            }
        }

        // Collect from method (overrides/augments class-level)
        if ($method !== null) {
            foreach ($method->getAttributes(RequireAuth::class) as $attr) {
                $authChecks[] = $attr->newInstance();
            }
            foreach ($method->getAttributes(RequireRole::class) as $attr) {
                $roleChecks[] = $attr->newInstance();
            }
            foreach ($method->getAttributes(RequirePermission::class) as $attr) {
                $permChecks[] = $attr->newInstance();
            }
            foreach ($method->getAttributes(Policy::class) as $attr) {
                $policyChecks[] = $attr->newInstance();
            }
        }

        // If no security attributes, return the resolver unchanged
        if ($authChecks === [] && $roleChecks === [] && $permChecks === [] && $policyChecks === []) {
            return $resolver;
        }

        return static function (mixed $root, array $args, GraphQLContext $ctx, \GraphQL\Type\Definition\ResolveInfo $info) use ($resolver, $authChecks, $roleChecks, $permChecks, $policyChecks) {
            $user = $ctx->user;

            // ── Auth Check ──────────────────────────────────────
            if ($authChecks !== [] && $user === null) {
                throw new Error('Unauthenticated. You must be logged in to access this resource.');
            }

            // ── Role Check ──────────────────────────────────────
            foreach ($roleChecks as $check) {
                /** @var RequireRole $check */
                if ($user === null) {
                    throw new Error('Unauthenticated. Role-based access requires authentication.');
                }

                $userRoles = self::extractRoles($user);
                $matched = array_intersect($check->roles, $userRoles);

                if ($check->mode === 'all' && count($matched) !== count($check->roles)) {
                    throw new Error(
                        sprintf('Forbidden. Missing required roles: %s', implode(', ', array_diff($check->roles, $userRoles)))
                    );
                }

                if ($check->mode === 'any' && $matched === []) {
                    throw new Error(
                        sprintf('Forbidden. Requires one of: %s', implode(', ', $check->roles))
                    );
                }
            }

            // ── Permission Check ────────────────────────────────
            foreach ($permChecks as $check) {
                /** @var RequirePermission $check */
                if ($user === null) {
                    throw new Error('Unauthenticated. Permission-based access requires authentication.');
                }

                $userPerms = self::extractPermissions($user);
                $matched = array_intersect($check->permissions, $userPerms);

                if ($check->mode === 'all' && count($matched) !== count($check->permissions)) {
                    throw new Error(
                        sprintf('Forbidden. Missing required permissions: %s', implode(', ', array_diff($check->permissions, $userPerms)))
                    );
                }

                if ($check->mode === 'any' && $matched === []) {
                    throw new Error(
                        sprintf('Forbidden. Requires one of: %s', implode(', ', $check->permissions))
                    );
                }
            }

            // ── Policy Gate Check ───────────────────────────────
            foreach ($policyChecks as $check) {
                /** @var Policy $check */
                $policy = $ctx->container->get($check->policyClass);
                $method = $check->method;

                if (!$policy->$method($user, $args)) {
                    throw new Error('Forbidden. Policy authorization failed.');
                }
            }

            return $resolver($root, $args, $ctx, $info);
        };
    }

    /**
     * Enforce security on auto-generated CRUD operations.
     *
     * Reads security attributes from the entity class (GraphQLResource target)
     * and wraps the generated resolver with the appropriate checks.
     *
     * @param callable     $resolver     The generated resolver
     * @param class-string $entityClass  The entity class to read security from
     * @param string       $operation    The CRUD operation (e.g. 'create', 'update', 'delete')
     *
     * @return callable
     */
    public static function wrapCrud(callable $resolver, string $entityClass, string $operation): callable
    {
        $class = new ReflectionClass($entityClass);
        return self::wrap($resolver, $class);
    }

    /**
     * Extract roles from a user object.
     * Supports: ->roles property, ->getRoles() method, or AuthenticatableInterface.
     *
     * @return list<string>
     */
    private static function extractRoles(object $user): array
    {
        if (method_exists($user, 'getRoles')) {
            return $user->getRoles();
        }

        if (property_exists($user, 'roles')) {
            return is_array($user->roles) ? $user->roles : [];
        }

        return [];
    }

    /**
     * Extract permissions from a user object.
     * Supports: ->permissions property, ->getPermissions() method.
     *
     * @return list<string>
     */
    private static function extractPermissions(object $user): array
    {
        if (method_exists($user, 'getPermissions')) {
            return $user->getPermissions();
        }

        if (property_exists($user, 'permissions')) {
            return is_array($user->permissions) ? $user->permissions : [];
        }

        return [];
    }
}
