<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Error;

use GraphQL\Error\ClientAware;

/**
 * Client-safe authorization error for access control failures.
 */
final class AuthorizationError extends \RuntimeException implements ClientAware
{
    /**
     * @param string          $message  Human-readable error message
     * @param int             $code     Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Unauthorized',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Whether the error message is safe to show to the client.
     *
     * @return bool
     */
    public function isClientSafe(): bool
    {
        return true;
    }
}
