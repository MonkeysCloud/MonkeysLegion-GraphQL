<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Subscription;

/**
 * Authenticates WebSocket connections from connection_init payload.
 *
 * Verifies JWT tokens or other credentials provided in the
 * connection initialization message.
 */
final class WsAuthenticator
{
    /** @var callable|null Custom authentication handler */
    private $authHandler;

    /**
     * @param callable|null $authHandler Custom handler: fn(array $payload): ?object
     */
    public function __construct(?callable $authHandler = null)
    {
        $this->authHandler = $authHandler;
    }

    /**
     * Authenticate a connection from its initialization payload.
     *
     * @param array<string, mixed> $payload The connection_init payload
     *
     * @return object|null The authenticated user object, or null
     */
    public function authenticate(array $payload): ?object
    {
        if ($this->authHandler !== null) {
            return ($this->authHandler)($payload);
        }

        // Default: extract token and attempt basic validation
        $token = $payload['Authorization'] ?? $payload['authorization']
            ?? $payload['token'] ?? $payload['authToken'] ?? null;

        if ($token === null) {
            return null;
        }

        // Remove 'Bearer ' prefix if present
        if (is_string($token) && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        // Basic JWT structure validation (3 base64url-encoded segments)
        if (!is_string($token) || substr_count($token, '.') !== 2) {
            return null;
        }

        // Decode the payload segment for basic user info
        $segments = explode('.', $token);
        $payloadJson = base64_decode(strtr($segments[1], '-_', '+/'), true);

        if ($payloadJson === false) {
            return null;
        }

        $claims = json_decode($payloadJson, true);

        if (!is_array($claims)) {
            return null;
        }

        // Check expiration
        if (isset($claims['exp']) && $claims['exp'] < time()) {
            return null;
        }

        // Return an anonymous object with the claims
        return (object) $claims;
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
