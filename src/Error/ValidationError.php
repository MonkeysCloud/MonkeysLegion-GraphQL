<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Error;

use GraphQL\Error\ClientAware;

/**
 * Client-safe validation error for input validation failures.
 *
 * Carries structured validation errors in extensions.
 */
final class ValidationError extends \RuntimeException implements ClientAware
{
    /**
     * @param string                        $message           Human-readable error message
     * @param array<string, array<string>>  $validationErrors  Validation errors keyed by field name
     * @param int                           $code              Error code
     * @param \Throwable|null               $previous          Previous exception
     */
    public function __construct(
        string $message = 'Validation failed',
        private readonly array $validationErrors = [],
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

    /**
     * Get the structured validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
