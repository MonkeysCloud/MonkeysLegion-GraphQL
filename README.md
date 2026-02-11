# MonkeysLegion-GraphQL

**Code-first GraphQL server for the MonkeysLegion framework** — PHP 8.4 attributes, PSR-15, DataLoader, subscriptions, and security out of the box.

## Requirements

- PHP 8.4+
- `webonyx/graphql-php` ^15.30

## Installation

```bash
composer require monkeyscloud/monkeyslegion-graphql
```

The `GraphQLProvider` is auto-registered via `composer.json` extra.

## Quick Start

### 1. Define a Type

```php
use MonkeysLegion\GraphQL\Attribute\{Type, Field};

#[Type(description: 'A user')]
final class UserType
{
    #[Field]
    public function id(User $root): int
    {
        return $root->id;
    }

    #[Field]
    public function name(User $root): string
    {
        return $root->name;
    }

    #[Field(description: 'Email address')]
    public function email(User $root): string
    {
        return $root->email;
    }
}
```

### 2. Define a Query

```php
use MonkeysLegion\GraphQL\Attribute\{Query, Arg};
use MonkeysLegion\GraphQL\Context\GraphQLContext;

#[Query(name: 'user', description: 'Get user by ID')]
final class GetUserQuery
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(
        mixed $root,
        #[Arg(description: 'User ID')] int $id,
        GraphQLContext $context,
    ): ?User {
        return $this->users->find($id);
    }
}
```

### 3. Define a Mutation

```php
use MonkeysLegion\GraphQL\Attribute\{Mutation, Arg};

#[Mutation(name: 'createUser', description: 'Create a new user')]
final class CreateUserMutation
{
    public function __construct(private UserRepository $users) {}

    public function __invoke(
        mixed $root,
        #[Arg] string $name,
        #[Arg] string $email,
    ): User {
        return $this->users->create($name, $email);
    }
}
```

### 4. Configure

```yaml
# config/graphql.mlc
graphql:
  endpoint: /graphql
  scan:
    directories:
      - app/GraphQL
  security:
    max_depth: 10
    max_complexity: 200
```

## Features

### Attributes

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[Type]` | Class | GraphQL object type |
| `#[Field]` | Method/Property | Object type field |
| `#[Query]` | Class | Root query field |
| `#[Mutation]` | Class | Root mutation field |
| `#[Subscription]` | Class | Subscription field |
| `#[Arg]` | Parameter | Argument metadata |
| `#[InputType]` | Class | Input object type |
| `#[Enum]` | Backed enum | Enum type |
| `#[InterfaceType]` | Class/Interface | Interface type |
| `#[UnionType]` | Class | Union type |
| `#[Middleware]` | Class/Method | Per-field middleware |

### Custom Scalars

- `DateTime` — ISO 8601 serialization
- `JSON` — Arbitrary JSON passthrough
- `Email` — Email format validation
- `URL` — URL format validation
- `Upload` — Multipart file upload

### Security

```yaml
graphql:
  security:
    max_depth: 10          # Query depth limiting
    max_complexity: 200    # Field cost analysis
    introspection: false   # Disable introspection in production
    persisted_queries: true # APQ with SHA256
    rate_limit:
      enabled: true
      max_requests: 100
      window_seconds: 60
```

### DataLoader (N+1 Prevention)

```php
use MonkeysLegion\GraphQL\Loader\DataLoader;

final class UserLoader extends DataLoader
{
    public function __construct(private UserRepository $users) {}

    protected function batchLoad(array $keys): array
    {
        $users = $this->users->findByIds($keys);
        return array_map(
            fn(int $id) => $users[$id] ?? null,
            $keys,
        );
    }
}
```

### Relay Pagination

```php
use MonkeysLegion\GraphQL\Type\ConnectionType;

// Automatically creates UserConnection, UserEdge, PageInfo types
$connectionType = ConnectionType::create('User', $userType);
```

### Subscriptions

```php
use MonkeysLegion\GraphQL\Attribute\Subscription;

#[Subscription(name: 'messageAdded', description: 'New message')]
final class MessageAddedSubscription
{
    public function __invoke(mixed $root): Message
    {
        return $root;
    }
}
```

Supports `graphql-ws` protocol with in-memory and Redis PubSub backends.

### File Uploads

Follows the [GraphQL multipart request spec](https://github.com/jaydenseric/graphql-multipart-request-spec):

```php
#[Mutation(name: 'uploadFile')]
final class UploadFileMutation
{
    public function __invoke(
        mixed $root,
        #[Arg] UploadedFileInterface $file,
    ): string {
        $file->moveTo('/uploads/' . $file->getClientFilename());
        return $file->getClientFilename();
    }
}
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `php ml graphql:schema:dump` | Dump schema as SDL |
| `php ml graphql:schema:validate` | Validate schema |
| `php ml graphql:cache:warm` | Warm schema cache |
| `php ml graphql:cache:clear` | Clear schema cache |
| `php ml graphql:introspect` | Dump introspection JSON |

## Entity Integration

When `monkeyslegion-entity` is installed, you can auto-map your entities to GraphQL types without writing boilerplate type classes.

### Entity Example

```php
use MonkeysLegion\Entity\Attribute\Entity;
use MonkeysLegion\Entity\Attribute\Id;
use MonkeysLegion\Entity\Attribute\Column;

#[Entity(table: 'products')]
class Product
{
    #[Id]
    public int $id;

    #[Column(type: 'varchar', length: 255)]
    public string $name;

    #[Column(type: 'text')]
    public string $description;

    #[Column(type: 'decimal')]
    public float $price;

    #[Column(type: 'boolean')]
    public bool $active;

    #[Column(type: 'datetime')]
    public \DateTimeImmutable $createdAt;
}
```

### Auto-Map Entities to GraphQL Types

```php
use MonkeysLegion\GraphQL\Scanner\EntityTypeMapper;

$mapper = new EntityTypeMapper();

// Maps all typed properties → GraphQL fields automatically:
//   int    → Int!        float  → Float!
//   string → String!     bool   → Boolean!
//   DateTime* → DateTime!  (custom scalar)
//   ?string → String     (nullable)
$typeConfig = $mapper->map(Product::class);
// Returns: ['name' => 'Product', 'fields' => ['id' => ..., 'name' => ..., ...]]

// Map multiple entities at once
$types = $mapper->mapAll([Product::class, Category::class, Order::class]);
```

### Auto-Generated CRUD Resolvers

Use `EntityResolver` to expose entities without manual resolver classes:

```php
use MonkeysLegion\GraphQL\Resolver\EntityResolver;
use MonkeysLegion\GraphQL\Type\ConnectionType;

// Single entity by ID
//   query { product(id: 42) { name price } }
$findProduct = EntityResolver::findById(
    Product::class,
    ProductRepository::class, // optional — defaults to Product::class . 'Repository'
);

// List all entities
//   query { products { name price active } }
$listProducts = EntityResolver::findAll(Product::class);

// Relay-style pagination with cursors
//   query { products(first: 10, after: "Y3Vyc29yOjk=") {
//     edges { node { name } cursor }
//     pageInfo { hasNextPage endCursor }
//     totalCount
//   }}
$paginatedProducts = EntityResolver::connection(Product::class);
```

### Full Example: Entity-Backed Schema

```php
use MonkeysLegion\GraphQL\Attribute\{Type, Field, Query, Arg};
use MonkeysLegion\GraphQL\Context\GraphQLContext;

// 1. Define the GraphQL type wrapping the entity
#[Type(description: 'A product in the catalog')]
final class ProductType
{
    #[Field]
    public function id(Product $root): int { return $root->id; }

    #[Field]
    public function name(Product $root): string { return $root->name; }

    #[Field]
    public function price(Product $root): float { return $root->price; }

    #[Field(description: 'Active in store?')]
    public function active(Product $root): bool { return $root->active; }

    #[Field(description: 'ISO 8601')]
    public function createdAt(Product $root): string {
        return $root->createdAt->format('c');
    }
}

// 2. Query resolver using the repository from DI
#[Query(name: 'product', description: 'Find product by ID')]
final class GetProductQuery
{
    public function __construct(private ProductRepository $products) {}

    public function __invoke(
        mixed $root,
        #[Arg(description: 'Product ID')] int $id,
        GraphQLContext $context,
    ): ?Product {
        return $this->products->find($id);
    }
}

// 3. List with filtering
#[Query(name: 'products', description: 'List products')]
final class ListProductsQuery
{
    public function __construct(private ProductRepository $products) {}

    public function __invoke(
        mixed $root,
        #[Arg(nullable: true)] ?bool $active,
        #[Arg(nullable: true, defaultValue: 20)] int $limit,
    ): array {
        if ($active !== null) {
            return $this->products->findByActive($active, $limit);
        }
        return $this->products->findAll($limit);
    }
}
```

## Route Registration

`GraphQLProvider` automatically registers routes with `monkeyslegion-router` when the application boots.

### Default Routes

| Method | Path | Handler | Description |
|--------|------|---------|-------------|
| `POST` | `/graphql` | `GraphQLMiddleware` | Queries & mutations |
| `GET` | `/graphql` | `GraphQLMiddleware` | Simple GET queries |
| `GET` | `/graphiql` | `GraphiQLMiddleware` | Interactive IDE (dev) |

### Configuration

```yaml
# config/graphql.mlc
graphql:
  endpoint: /graphql            # Change the endpoint path
  graphiql_enabled: true        # Disable GraphiQL in production
  graphiql_endpoint: /graphiql  # Custom GraphiQL path
  debug: false                  # Enable for detailed error traces
  scan_dirs:
    - app/GraphQL               # Where to find Type/Query/Mutation classes
  scan_namespace: App\GraphQL   # PSR-4 namespace for scanned classes
  security:
    max_depth: 10
    max_complexity: 200
    introspection: true         # Disable in production
    persisted_queries: false
    rate_limit:
      max_requests: 100
      window_seconds: 60
  cache:
    enabled: false
    ttl: 3600                   # Schema cache TTL in seconds
  subscriptions:
    enabled: false
    driver: memory              # 'memory' or 'redis'
    host: 0.0.0.0
    port: 6001
    redis_dsn: redis://127.0.0.1:6379
```

### How Route Registration Works

The `GraphQLProvider::register()` method is called automatically during application bootstrap (via the `monkeyslegion` extra in `composer.json`). Here's what happens:

```php
// This happens automatically — no manual setup needed.
// The provider:
//   1. Reads config/graphql.mlc
//   2. Registers all GraphQL services in the DI container
//   3. Registers routes with MonkeysLegion\Router\Router

// Routes are registered as closures that delegate to PSR-15 middleware:
$router->post('/graphql', $graphqlHandler, 'graphql');
$router->get('/graphql', $graphqlHandler, 'graphql.get');
$router->get('/graphiql', $graphiqlHandler, 'graphiql'); // if enabled
```

### Custom Route Middleware

Stack your own middleware (auth, CORS, rate-limiting) alongside GraphQL:

```php
use MonkeysLegion\GraphQL\Attribute\Middleware;

// Per-resolver middleware
#[Middleware('App\Middleware\AuthMiddleware')]
#[Middleware('App\Middleware\RateLimitMiddleware')]
#[Query(name: 'adminUsers')]
final class AdminUsersQuery
{
    public function __invoke(mixed $root, GraphQLContext $context): array
    {
        // Only reached if auth + rate-limit pass
        return $context->container->get(UserRepository::class)->findAdmins();
    }
}
```

### Testing the Endpoint

```bash
# Simple query
curl -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query": "{ product(id: 1) { name price } }"}'

# Mutation
curl -X POST http://localhost:8080/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query": "mutation { createProduct(name: \"Widget\", price: 9.99) { id name } }"}'

# GET request (simple queries only)
curl 'http://localhost:8080/graphql?query=\{products\{name\}\}'

# Open GraphiQL IDE in browser
open http://localhost:8080/graphiql
```

## Facade

```php
use MonkeysLegion\GraphQL\GraphQL;

// Execute a query programmatically
$result = GraphQL::execute('{ user(id: 1) { name } }');

// Publish a subscription event
GraphQL::publish('messageAdded', $message);

// Get the built schema
$schema = GraphQL::schema();
```

## License

MIT — see [LICENSE](LICENSE) for details.