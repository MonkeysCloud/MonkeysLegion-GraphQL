<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Attribute;

use MonkeysLegion\GraphQL\Attribute\Broadcast;
use MonkeysLegion\GraphQL\Attribute\Complexity;
use MonkeysLegion\GraphQL\Attribute\GraphQLResource;
use MonkeysLegion\GraphQL\Attribute\Middleware;
use MonkeysLegion\GraphQL\Attribute\Paginate;
use MonkeysLegion\GraphQL\Attribute\Policy;
use MonkeysLegion\GraphQL\Attribute\RequireAuth;
use MonkeysLegion\GraphQL\Attribute\RequirePermission;
use MonkeysLegion\GraphQL\Attribute\RequireRole;
use MonkeysLegion\GraphQL\Attribute\Validate;
use PHPUnit\Framework\TestCase;

final class Phase2to5AttributeTest extends TestCase
{
    public function testGraphQLResource(): void
    {
        $attr = new GraphQLResource(repositoryClass: 'App\\Repo\\UserRepo');
        $this->assertSame('App\\Repo\\UserRepo', $attr->repositoryClass);
    }

    public function testGraphQLResourceHasOperation(): void
    {
        $attr = new GraphQLResource(
            repositoryClass: 'App\\Repo',
            operations: ['find', 'list', 'create'],
        );
        $this->assertTrue($attr->hasOperation('find'));
        $this->assertTrue($attr->hasOperation('create'));
        $this->assertFalse($attr->hasOperation('delete'));
    }

    public function testPaginate(): void
    {
        $attr = new Paginate(type: 'cursor', defaultCount: 25);
        $this->assertSame('cursor', $attr->type);
        $this->assertSame(25, $attr->defaultCount);
    }

    public function testRequireAuth(): void
    {
        $attr = new RequireAuth(guard: 'jwt');
        $this->assertSame('jwt', $attr->guard);

        $default = new RequireAuth();
        $this->assertNull($default->guard);
    }

    public function testRequireRoleString(): void
    {
        $attr = new RequireRole('admin');
        $this->assertSame(['admin'], $attr->roles);
        $this->assertSame('any', $attr->mode);
    }

    public function testRequireRoleArray(): void
    {
        $attr = new RequireRole(['admin', 'editor'], mode: 'all');
        $this->assertSame(['admin', 'editor'], $attr->roles);
        $this->assertSame('all', $attr->mode);
    }

    public function testRequirePermissionString(): void
    {
        $attr = new RequirePermission('posts.create');
        $this->assertSame(['posts.create'], $attr->permissions);
        $this->assertSame('all', $attr->mode);
    }

    public function testRequirePermissionArray(): void
    {
        $attr = new RequirePermission(['posts.create', 'posts.edit'], mode: 'any');
        $this->assertSame(['posts.create', 'posts.edit'], $attr->permissions);
        $this->assertSame('any', $attr->mode);
    }

    public function testPolicy(): void
    {
        $attr = new Policy(policyClass: 'App\\Policy\\PostPolicy', method: 'canEdit');
        $this->assertSame('App\\Policy\\PostPolicy', $attr->policyClass);
        $this->assertSame('canEdit', $attr->method);
    }

    public function testPolicyDefaultMethod(): void
    {
        $attr = new Policy(policyClass: 'App\\Policy\\PostPolicy');
        $this->assertSame('authorize', $attr->method);
    }

    public function testValidateWithString(): void
    {
        $attr = new Validate('required|email|min_length:3');
        $this->assertSame(['required', 'email', 'min_length:3'], $attr->rules);
    }

    public function testValidateWithArray(): void
    {
        $attr = new Validate(['required', 'string']);
        $this->assertSame(['required', 'string'], $attr->rules);
    }

    public function testBroadcast(): void
    {
        $attr = new Broadcast(channel: 'posts', event: 'postCreated', shouldQueue: true);
        $this->assertSame('posts', $attr->channel);
        $this->assertSame('postCreated', $attr->event);
        $this->assertTrue($attr->shouldQueue);
    }

    public function testBroadcastDefaults(): void
    {
        $attr = new Broadcast(channel: 'orders');
        $this->assertSame('orders', $attr->channel);
        $this->assertNull($attr->event);
        $this->assertFalse($attr->shouldQueue);
    }

    public function testComplexity(): void
    {
        $attr = new Complexity(cost: 5, multiplier: 10);
        $this->assertSame(5, $attr->cost);
        $this->assertSame(10, $attr->multiplier);
    }

    public function testComplexityDefaults(): void
    {
        $attr = new Complexity();
        $this->assertSame(1, $attr->cost);
        $this->assertSame(1, $attr->multiplier);
    }

    public function testMiddlewareString(): void
    {
        $attr = new Middleware('App\\Middleware\\LoggingMiddleware');
        $this->assertSame(['App\\Middleware\\LoggingMiddleware'], $attr->middleware);
    }

    public function testMiddlewareArray(): void
    {
        $attr = new Middleware(['Mw1', 'Mw2']);
        $this->assertSame(['Mw1', 'Mw2'], $attr->middleware);
    }
}
