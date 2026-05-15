<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Security;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Attribute\RequireAuth;
use MonkeysLegion\GraphQL\Attribute\RequireRole;
use MonkeysLegion\GraphQL\Attribute\RequirePermission;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Loader\DataLoaderRegistry;
use MonkeysLegion\GraphQL\Security\SecurityEnforcer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class SecurityEnforcerTest extends TestCase
{
    private function makeContext(?object $user = null): GraphQLContext
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $loaders = new DataLoaderRegistry();

        return new GraphQLContext($request, $user, $container, $loaders);
    }

    private function makeResolveInfo(): ResolveInfo
    {
        return $this->createMock(ResolveInfo::class);
    }

    public function testUnauthenticatedUserIsRejected(): void
    {
        $resolver = static fn() => 'secret data';

        // Create a dummy class with RequireAuth
        $ref = new \ReflectionClass(new #[RequireAuth] class {});

        $wrapped = SecurityEnforcer::wrap($resolver, $ref);

        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/Unauthenticated/');

        $wrapped(null, [], $this->makeContext(null), $this->makeResolveInfo());
    }

    public function testAuthenticatedUserPassesAuthCheck(): void
    {
        $resolver = static fn() => 'secret data';

        $ref = new \ReflectionClass(new #[RequireAuth] class {});
        $wrapped = SecurityEnforcer::wrap($resolver, $ref);

        $user = new class {
            public function getRoles(): array { return []; }
            public function getPermissions(): array { return []; }
        };

        $result = $wrapped(null, [], $this->makeContext($user), $this->makeResolveInfo());
        $this->assertSame('secret data', $result);
    }

    public function testRoleCheckPassesWithMatchingRole(): void
    {
        $resolver = static fn() => 'admin data';

        $ref = new \ReflectionClass(new #[RequireRole('admin')] class {});
        $wrapped = SecurityEnforcer::wrap($resolver, $ref);

        $user = new class {
            public function getRoles(): array { return ['admin', 'user']; }
            public function getPermissions(): array { return []; }
        };

        $result = $wrapped(null, [], $this->makeContext($user), $this->makeResolveInfo());
        $this->assertSame('admin data', $result);
    }

    public function testRoleCheckFailsWithoutMatchingRole(): void
    {
        $resolver = static fn() => 'admin data';

        $ref = new \ReflectionClass(new #[RequireRole('superadmin')] class {});
        $wrapped = SecurityEnforcer::wrap($resolver, $ref);

        $user = new class {
            public function getRoles(): array { return ['user']; }
            public function getPermissions(): array { return []; }
        };

        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/Forbidden/');

        $wrapped(null, [], $this->makeContext($user), $this->makeResolveInfo());
    }

    public function testPermissionCheckPassesWithMatchingPermissions(): void
    {
        $resolver = static fn() => 'protected data';

        $ref = new \ReflectionClass(new #[RequirePermission(['posts.create', 'posts.edit'])] class {});
        $wrapped = SecurityEnforcer::wrap($resolver, $ref);

        $user = new class {
            public function getRoles(): array { return []; }
            public function getPermissions(): array { return ['posts.create', 'posts.edit', 'posts.delete']; }
        };

        $result = $wrapped(null, [], $this->makeContext($user), $this->makeResolveInfo());
        $this->assertSame('protected data', $result);
    }

    public function testPermissionCheckFailsMissingPermission(): void
    {
        $resolver = static fn() => 'protected data';

        // mode=all requires ALL permissions
        $ref = new \ReflectionClass(new #[RequirePermission(['posts.create', 'posts.nuke'], mode: 'all')] class {});
        $wrapped = SecurityEnforcer::wrap($resolver, $ref);

        $user = new class {
            public function getRoles(): array { return []; }
            public function getPermissions(): array { return ['posts.create']; }
        };

        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/Missing required permissions/');

        $wrapped(null, [], $this->makeContext($user), $this->makeResolveInfo());
    }

    public function testNoSecurityAttributesPassesThrough(): void
    {
        $resolver = static fn() => 'public data';

        $ref = new \ReflectionClass(new class {});
        $wrapped = SecurityEnforcer::wrap($resolver, $ref);

        // Wrapped should be the original resolver (no security applied)
        $result = $wrapped(null, [], $this->makeContext(null), $this->makeResolveInfo());
        $this->assertSame('public data', $result);
    }
}
