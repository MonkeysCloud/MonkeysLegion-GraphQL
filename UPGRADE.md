# Upgrade Guide — v1.x → v2.0

## Breaking Changes

### Attributes

- `#[Type]` no longer extends `GraphQL\Type\Definition\ObjectType`. Types are now plain PHP classes with `#[Field]` methods.
- `#[Query]` and `#[Mutation]` classes now use `__invoke()` as the resolver method.
- `#[Subscription]` moved from `MonkeysLegion\GraphQL\Attribute\Subscription` (same namespace, new constructor params).

**Before (v1):**
```php
#[Type]
class UserType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'User',
            'fields' => [...]
        ]);
    }
}
```

**After (v2):**
```php
#[Type(description: 'A user')]
final class UserType
{
    #[Field]
    public function name(User $root): string
    {
        return $root->name;
    }
}
```

### Configuration

- Config moved from array-based to `.mlc` format at `config/graphql.mlc`.
- All settings now accessed through `GraphQLConfig` instead of raw arrays.

### WebSocket Protocol

- Changed from `subscriptions-transport-ws` to `graphql-ws`.
- `WsHandler` removed — use `SubscriptionServer` which implements the new protocol.
- Ratchet/React dependencies removed.

### Dependencies

**Removed:**
- `cboden/ratchet`
- `react/event-loop`
- `react/socket`

**Added:**
- `psr/simple-cache: ^3.0`
- `psr/container: ^2.0`
- `monkeyscloud/monkeyslegion-core: ^1.0`
- `monkeyscloud/monkeyslegion-di: ^1.0`
- `monkeyscloud/monkeyslegion-router: ^1.0`
- `monkeyscloud/monkeyslegion-mlc: ^1.0`

### Removed Classes

| v1 Class | v2 Replacement |
|----------|----------------|
| `Schema\SchemaFactory` | `Builder\SchemaBuilder` |
| `Support\Scanner` | `Scanner\AttributeScanner` |
| `Execution\Executor` | `Executor\QueryExecutor` |
| `WebSocket\WsHandler` | `Subscription\SubscriptionServer` |
| `Cli\SchemaCommand` | `Command\SchemaDumpCommand` |

### PubSub Interface

```diff
-interface PubSubInterface
-{
-    public function subscribe(string $topic, callable $cb): void;
-    public function publish(string $topic, $data): void;
-}
+interface PubSubInterface
+{
+    public function publish(string $channel, mixed $payload): void;
+    public function subscribe(string $channel, callable $callback): string;
+    public function unsubscribe(string $subscriptionId): void;
+}
```

### Middleware

- `GraphQLMiddleware` is now PSR-15 compliant.
- Routes are matched via `GraphQLConfig::endpoint()` instead of constructor parameters.

## Migration Steps

1. Update `composer.json` to require `monkeyscloud/monkeyslegion-graphql: ^2.0`
2. Run `composer update`
3. Create `config/graphql.mlc` from the template
4. Rewrite type classes to use `#[Type]` + `#[Field]` attributes
5. Rewrite query/mutation classes to use `__invoke()` pattern
6. Update subscription code to use `graphql-ws` protocol
7. Replace `WsHandler` with `SubscriptionServer`
8. Update PubSub consumers to use new interface
9. Run `php ml graphql:schema:validate` to verify
