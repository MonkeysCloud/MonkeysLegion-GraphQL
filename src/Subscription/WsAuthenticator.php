<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Subscription;

/**
 * Authenticates WebSocket connections from connection_init payload.
 *
 * **IMPORTANT**: You MUST provide a custom `$authHandler` that verifies
 * tokens using a proper JWT library or your own authentication service.
 * Without a custom handler, authentication will reject all connections
 * with a token (connections without a token will be allowed as anonymous).
 *
 * Example using monkeyslegion-auth:
 * ```php
 * new WsAuthenticator(function (array $payload) use ($jwtService): ?object {
 *     $token = $payload['Authorization'] ?? $payload['token'] ?? null;
 *     if ($token === null) return null;
 *     return $jwtService->verify(str_replace('Bearer ', '', $token));
 * });
 * ```
 */
final class WsAuthenticator
{
    /** @var callable|null Custom authentication handler */
    private $authHandler;

    /**
     * @param callable|null $authHandler Custom handler: fn(array $payload): ?object
     *                                   You MUST provide this for authenticated subscriptions.
     */
    public function __construct(?callable $authHandler = null)
    {
        $this->authHandler = $authHandler;
    }

    /**
     * Authenticate a connection from its initialization payload.
     *
     * If no custom authHandler is provided, connections that include an
     * authentication token will be rejected (returns null). Connections
     * without a token are allowed as anonymous (returns null).
     *
     * @param array<string, mixed> $payload The connection_init payload
     *
     * @return object|null The authenticated user object, or null if unauthenticated
     *
     * @throws \RuntimeException If a token is present but no authHandler is configured
     */
    public function authenticate(array $payload): ?object
    {
        if ($this->authHandler !== null) {
            return ($this->authHandler)($payload);
        }

        // Check if client is trying to authenticate without a handler configured
        $token = $payload['Authorization'] ?? $payload['authorization']
            ?? $payload['token'] ?? $payload['authToken'] ?? null;

        if ($token !== null) {
            throw new \RuntimeException(
                'WebSocket authentication token received but no authHandler is configured. '
                . 'You must provide a custom authHandler to WsAuthenticator that properly '
                . 'verifies token signatures. Accepting unverified tokens is a security vulnerability.',
            );
        }

        // No token provided — allow as anonymous connection
        return null;
    }

    /**
     * Set a custom authentication handler.
     *
     * @param callable $handler fn(array $payload): ?object
     *
     * @return void
     */
    public function setHandler(callable $handler): void
    {
        $this->authHandler = $handler;
    }
}
