# Changelog

All notable changes to MonkeysLegion-GraphQL are documented in this file.

## [2.0.0] — 2026-02-10

### Complete Rewrite

This is a complete rewrite of the GraphQL package with a new architecture.

### Added

- **Attribute-driven schema definition** — `#[Type]`, `#[Field]`, `#[Query]`, `#[Mutation]`, `#[Subscription]`, `#[Arg]`, `#[InputType]`, `#[Enum]`, `#[InterfaceType]`, `#[UnionType]`, `#[Middleware]`
- **`GraphQLConfig`** — typed MLC-based configuration
- **`GraphQLContext`** — immutable execution context with request, user, container, and DataLoaderRegistry
- **`AttributeScanner`** — recursive directory scanning for annotated classes
- **Builder pipeline** — `SchemaBuilder`, `TypeBuilder`, `FieldBuilder`, `InputTypeBuilder`, `EnumBuilder`, `ArgumentBuilder`
- **Custom scalars** — `DateTime`, `JSON`, `Email`, `URL`, `Upload`
- **PSR-15 middleware** — `GraphQLMiddleware`, `GraphiQLMiddleware`, `UploadMiddleware`
- **Security features** — `DepthLimiter`, `ComplexityAnalyzer`, `IntrospectionControl`, `PersistedQueries`, `RateLimiter`
- **DataLoader** — abstract `DataLoader` with batch loading and per-request caching, `DataLoaderRegistry`
- **Batch execution** — `BatchExecutor` for multi-operation requests
- **Schema caching** — `SchemaCache` (PSR-16) and `SchemaCacheWarmer`
- **Subscriptions** — `graphql-ws` protocol, `PubSubInterface`, `InMemoryPubSub`, `RedisPubSub`, `SubscriptionManager`, `SubscriptionServer`, `WsAuthenticator`
- **Input validation** — `InputValidator` and `RuleSet`
- **Entity integration** — `EntityTypeMapper` and `EntityResolver`
- **Relay pagination** — `ConnectionType`, `EdgeType`, `PageInfoType`
- **GraphQL facade** — static `GraphQL::execute()`, `GraphQL::publish()`, `GraphQL::schema()`
- **Service provider** — `GraphQLProvider` for auto-registration
- **CLI commands** — `graphql:schema:dump`, `graphql:schema:validate`, `graphql:cache:warm`, `graphql:cache:clear`, `graphql:introspect`
- **Error handling** — `ErrorHandler`, `ValidationError`, `AuthorizationError`

### Changed

- Minimum PHP version raised to 8.4
- WebSocket protocol changed from `subscriptions-transport-ws` to `graphql-ws`
- Configuration format changed from arrays to `.mlc` files
- Types no longer extend webonyx base classes

### Removed

- `Schema\SchemaFactory` (replaced by `Builder\SchemaBuilder`)
- `Support\Scanner` (replaced by `Scanner\AttributeScanner`)
- `Execution\Executor` (replaced by `Executor\QueryExecutor`)
- `WebSocket\WsHandler` (replaced by `Subscription\SubscriptionServer`)
- Ratchet/React dependencies
