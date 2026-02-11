<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Error;

use GraphQL\Error\ClientAware;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;

/**
 * Handles GraphQL error formatting and categorization.
 *
 * Development mode: includes debugMessage, trace, and locations.
 * Production mode: generic messages for unexpected errors, structured
 * validation errors in extensions. ClientAware exceptions pass through.
 */
final class ErrorHandler
{
    /**
     * @param bool $debug Whether debug mode is enabled
     */
    public function __construct(
        private readonly bool $debug = false,
    ) {}

    /**
     * Format a GraphQL error for the response.
     *
     * @param Error $error The error to format
     *
     * @return array<string, mixed>
     */
    public function format(Error $error): array
    {
        $formatted = FormattedError::createFromException($error);

        $previous = $error->getPrevious();

        // ClientAware errors pass through with their message and category
        if ($previous instanceof ClientAware && $previous->isClientSafe()) {
            $formatted['message'] = $previous->getMessage();
            $formatted['extensions'] = array_merge(
                $formatted['extensions'] ?? [],
                ['category' => $this->categorize($previous)],
            );

            if ($previous instanceof ValidationError) {
                $formatted['extensions']['validation'] = $previous->getValidationErrors();
            }

            return $formatted;
        }

        // Debug mode: include details
        if ($this->debug) {
            if ($previous !== null) {
                $formatted['extensions'] = array_merge(
                    $formatted['extensions'] ?? [],
                    [
                        'debugMessage' => $previous->getMessage(),
                        'trace'        => $previous->getTraceAsString(),
                        'category'     => 'internal',
                    ],
                );
            }
            return $formatted;
        }

        // Production mode: generic message for unexpected errors
        if ($previous !== null && !($previous instanceof ClientAware)) {
            $formatted['message'] = 'Internal server error';
            $formatted['extensions'] = ['category' => 'internal'];
        }

        return $formatted;
    }

    /**
     * Create a callable formatter for use with webonyx executor.
     *
     * @return callable(Error): array<string, mixed>
     */
    public function formatter(): callable
    {
        return fn(Error $error): array => $this->format($error);
    }

    /**
     * Create a callable error handler for use with webonyx executor.
     *
     * @return callable(array<Error>, callable): array<array<string, mixed>>
     */
    public function handler(): callable
    {
        return function (array $errors, callable $formatter): array {
            return array_map($this->formatter(), $errors);
        };
    }

    /**
     * Categorize an error by its type.
     *
     * @param \Throwable $error The error to categorize
     *
     * @return string One of: graphql, validation, authorization, internal
     */
    private function categorize(\Throwable $error): string
    {
        return match (true) {
            $error instanceof ValidationError    => 'validation',
            $error instanceof AuthorizationError => 'authorization',
            $error instanceof Error              => 'graphql',
            default                              => 'internal',
        };
    }
}
