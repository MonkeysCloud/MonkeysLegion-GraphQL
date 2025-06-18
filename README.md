# 🐒 MonkeysLegion GraphQL

GraphQL adapter for the **MonkeysLegion** ecosystem – zero Symfony baggage, code-first PHP 8 attributes, PSR-15 middleware, and GraphiQL out of the box.

| Feature                                                | Status |
|--------------------------------------------------------|--------|
| `/graphql` POST/GET endpoint                           | ✅      |
| Attribute-driven **Type / Query / Mutation** discovery | ✅      |
| Auto-DI & PSR-15 middleware binding via _providers_    | ✅      |
| GraphiQL playground in dev                             | ✅      |
| Subscriptions (WebSocket)                              | ✅      |

---

## Installation

```bash
composer require monkeyscloud/monkeyslegion-graphql
```

The package adds itself to composer.json → extra.monkeyslegion.providers
so your bootstrap auto-registers its services – no manual edits to
config/app.php are required.

### Bootstrap hook
Make sure your public/index.php (or CLI kernel) contains the
provider-loading loop described in the docs.

## Quick start
### 1 · Define your first type & query
```php
// app/GraphQL/Types/PostType.php
namespace App\GraphQL\Types;

use MonkeysLegion\GraphQL\Attribute\Type;
use GraphQL\Type\Definition\Type as GQLType;
use GraphQL\Type\Definition\ObjectType;

#[Type]
final class PostType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name'   => 'Post',
            'fields' => [
                'id'    => GQLType::nonNull(GQLType::id()),
                'title' => GQLType::string(),
                'body'  => GQLType::string(),
            ],
        ]);
    }
}
```
```php
// app/GraphQL/Query/BlogQuery.php
namespace App\GraphQL\Query;

use MonkeysLegion\GraphQL\Attribute\Query;
use GraphQL\Type\Definition\ResolveInfo;
use App\Repository\PostRepository;

#[Query(name: 'posts')]
final class BlogQuery
{
    public function __construct(private PostRepository $repo) {}

    public function __invoke(mixed $root, array $args, mixed $ctx, ResolveInfo $info): array
    {
        return $this->repo->findAll();
    }
}
```
Drop more classes with #[Type], #[Query], #[Mutation] and they’ll be auto-discovered at hot-reload.

## 2 · Define a Subscription
```php
// app/GraphQL/Subscription/CounterSub.php
namespace App\GraphQL\Subscription;

use MonkeysLegion\GraphQL\Attribute\Subscription;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GQLType;
use MonkeysLegion\GraphQL\Subscription\PubSubInterface;

#[Subscription(name: 'counter')]
final class CounterSub extends ObjectType
{
    public function __construct(PubSubInterface $pubsub)
    {
        parent::__construct([
            'name'       => 'CounterSub',
            'fields'     => [
                'count' => ['type' => GQLType::int()],
            ],
            'subscribe'  => fn() => $pubsub->subscribe('counter.tick', fn($v) => ['count' => $v]),
            'resolve'    => fn($root) => $root,
        ]);
    }
}
```

### 3 · Hit the endpoint
```bash
curl -X POST http://localhost:8000/graphql \
     -H "Content-Type: application/json" \
     -d '{"query":"{ posts { id title } }"}'
```
## How it works
```php
app/GraphQL/*
   ├─ #[Type]          → GraphQL\Type\Definition\ObjectType
   ├─ #[Query]         → fields merged into root Query
   ├─ #[Mutation]      → fields merged into root Mutation
   └─ #[Subscription]  → root “Subscription” fields
```

1. Scanner crawls app/GraphQL/ for the attributes.
2. SchemaFactory builds a Webonyx Schema on boot.
3. Executor runs each request with MonkeysLegion services injected into resolvers.
4. SubscriptionServer + WsHandler manage WebSockets on port 6001.
5. Middleware pipes into your existing PSR-15 stack at /graphql.

| Attribute             | Target                     | Purpose                                                                 |
|-----------------------|----------------------------|-------------------------------------------------------------------------|
| #[Type]               | class (extends ObjectType) | Registers a reusable GraphQL type.                                      |
| #[Query(name)]        | class (callable)           | Adds a field to root Query; method/callable must return resolver value. |
| #[Mutation(name)]     | class (callable)           | Adds a field to root Mutation.                                          |
| #[Subscription(name)] | class (callable)           | Adds root Subscription.                                                 |

## Contributing
1.	git clone & composer install
2.	vendor/bin/phpunit – tests must stay green
3.	PRs against main, follow PSR-12, add doc-blocks / tests

MIT license – happy hacking! 🐒🚀