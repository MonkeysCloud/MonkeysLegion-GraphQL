<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Attribute;

use MonkeysLegion\GraphQL\Attribute\Arg;
use MonkeysLegion\GraphQL\Attribute\Enum;
use MonkeysLegion\GraphQL\Attribute\Field;
use MonkeysLegion\GraphQL\Attribute\InputType;
use MonkeysLegion\GraphQL\Attribute\InterfaceType;
use MonkeysLegion\GraphQL\Attribute\Middleware;
use MonkeysLegion\GraphQL\Attribute\Mutation;
use MonkeysLegion\GraphQL\Attribute\Query;
use MonkeysLegion\GraphQL\Attribute\Subscription;
use MonkeysLegion\GraphQL\Attribute\Type;
use MonkeysLegion\GraphQL\Attribute\UnionType;
use PHPUnit\Framework\TestCase;

final class AttributeTest extends TestCase
{
    public function testTypeDefaultValues(): void
    {
        $attr = new Type();
        self::assertNull($attr->name);
        self::assertNull($attr->description);
    }

    public function testTypeCustomValues(): void
    {
        $attr = new Type(name: 'User', description: 'A user');
        self::assertSame('User', $attr->name);
        self::assertSame('A user', $attr->description);
    }

    public function testFieldDefaults(): void
    {
        $attr = new Field();
        self::assertNull($attr->name);
        self::assertNull($attr->type);
        self::assertNull($attr->description);
        self::assertNull($attr->deprecationReason);
        self::assertNull($attr->complexity);
    }

    public function testFieldCustom(): void
    {
        $attr = new Field(
            name: 'email',
            type: 'String!',
            description: 'Email address',
            deprecationReason: 'Use emailAddress',
            complexity: 5,
        );
        self::assertSame('email', $attr->name);
        self::assertSame('String!', $attr->type);
        self::assertSame('Email address', $attr->description);
        self::assertSame('Use emailAddress', $attr->deprecationReason);
        self::assertSame(5, $attr->complexity);
    }

    public function testQueryDefaults(): void
    {
        $attr = new Query(name: 'users');
        self::assertSame('users', $attr->name);
        self::assertNull($attr->description);
        self::assertNull($attr->type);
    }

    public function testMutationDefaults(): void
    {
        $attr = new Mutation(name: 'createUser');
        self::assertSame('createUser', $attr->name);
        self::assertNull($attr->description);
    }

    public function testArgDefaults(): void
    {
        $attr = new Arg();
        self::assertNull($attr->name);
        self::assertNull($attr->type);
        self::assertNull($attr->description);
        self::assertFalse($attr->nullable); // Default is false
        self::assertSame(Arg::UNDEFINED, $attr->defaultValue);
        self::assertFalse($attr->hasDefaultValue());
    }

    public function testArgCustom(): void
    {
        $attr = new Arg(
            name: 'id',
            type: 'Int!',
            description: 'The ID',
            nullable: false,
            defaultValue: 0,
        );
        self::assertSame('id', $attr->name);
        self::assertSame('Int!', $attr->type);
        self::assertFalse($attr->nullable);
        self::assertSame(0, $attr->defaultValue);
        self::assertTrue($attr->hasDefaultValue());
    }

    public function testInputType(): void
    {
        $attr = new InputType(name: 'CreateUserInput', description: 'Input');
        self::assertSame('CreateUserInput', $attr->name);
        self::assertSame('Input', $attr->description);
    }

    public function testEnumAttr(): void
    {
        $attr = new Enum(name: 'Status', description: 'Post status');
        self::assertSame('Status', $attr->name);
        self::assertSame('Post status', $attr->description);
    }

    public function testInterfaceType(): void
    {
        $attr = new InterfaceType(name: 'Node', description: 'Relay node');
        self::assertSame('Node', $attr->name);
        self::assertSame('Relay node', $attr->description);
    }

    public function testUnionType(): void
    {
        $attr = new UnionType(name: 'SearchResult', types: ['User', 'Post']);
        self::assertSame('SearchResult', $attr->name);
        self::assertSame(['User', 'Post'], $attr->types);
    }

    public function testSubscription(): void
    {
        $attr = new Subscription(name: 'messageAdded', description: 'New msg');
        self::assertSame('messageAdded', $attr->name);
        self::assertSame('New msg', $attr->description);
    }

    public function testMiddleware(): void
    {
        $attr = new Middleware('App\\Auth\\Guard');
        self::assertSame(['App\\Auth\\Guard'], $attr->middleware);
    }

    public function testMiddlewareArray(): void
    {
        $attr = new Middleware(['App\\Auth\\Guard', 'App\\Log\\Logger']);
        self::assertSame(['App\\Auth\\Guard', 'App\\Log\\Logger'], $attr->middleware);
    }

    public function testTypeIsAttribute(): void
    {
        $ref = new \ReflectionClass(Type::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        self::assertNotEmpty($attrs);
    }

    public function testFieldTargets(): void
    {
        $ref = new \ReflectionClass(Field::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        self::assertNotEmpty($attrs);

        $instance = $attrs[0]->newInstance();
        $flags = $instance->flags;
        self::assertTrue(($flags & \Attribute::TARGET_METHOD) !== 0);
        self::assertTrue(($flags & \Attribute::TARGET_PROPERTY) !== 0);
    }

    public function testMiddlewareIsRepeatable(): void
    {
        $ref = new \ReflectionClass(Middleware::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        self::assertNotEmpty($attrs);

        $instance = $attrs[0]->newInstance();
        // IS_REPEATABLE is set separately from flags
        self::assertTrue(($instance->flags & \Attribute::IS_REPEATABLE) !== 0);
    }
}
