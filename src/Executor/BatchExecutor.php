<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Executor;

use GraphQL\Type\Schema;
use MonkeysLegion\GraphQL\Context\GraphQLContext;

/**
 * Executes batched GraphQL queries.
 *
 * Processes multiple GraphQL operations in a single HTTP request,
 * flushing DataLoaders between operations for optimal batching.
 */
final class BatchExecutor
{
    /**
     * @param QueryExecutor $executor Single-query executor
     */
    public function __construct(
        private readonly QueryExecutor $executor,
    ) {}

    /**
     * Execute multiple GraphQL operations as a batch.
     *
     * @param Schema         $schema     The GraphQL schema
     * @param GraphQLContext $context    Execution context
     * @param array<array{query: string|null, variables?: array<string, mixed>|null, operationName?: string|null}> $operations Operations to execute
     * @param array<\GraphQL\Validator\Rules\ValidationRule> $validationRules Validation rules
     *
     * @return array<array{data?: array<string, mixed>|null, errors?: array<array<string, mixed>>}>
     */
    public function execute(
        Schema $schema,
        GraphQLContext $context,
        array $operations,
        array $validationRules = [],
    ): array {
        $results = [];

        foreach ($operations as $operation) {
            $query = $operation['query'] ?? null;

            if ($query === null) {
                $results[] = ['errors' => [['message' => 'No query string provided.']]];
                continue;
            }

            $results[] = $this->executor->execute(
                $schema,
                $query,
                $context,
                $operation['variables'] ?? null,
                $operation['operationName'] ?? null,
                $validationRules,
            );

            // Flush DataLoaders between operations
            $context->loaders->flushAll();
        }

        return $results;
    }
}
