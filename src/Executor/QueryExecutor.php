<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Executor;

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use MonkeysLegion\GraphQL\Error\ErrorHandler;

/**
 * Executes a single GraphQL query against the schema.
 *
 * Wraps webonyx/graphql-php execution with context injection, validation
 * rules, and error formatting.
 */
final class QueryExecutor
{
    /**
     * @param ErrorHandler $errorHandler Error handler for formatting
     */
    public function __construct(
        private readonly ErrorHandler $errorHandler,
    ) {}

    /**
     * Execute a GraphQL query.
     *
     * @param Schema                         $schema        The GraphQL schema
     * @param string                         $query         The query string
     * @param GraphQLContext                 $context       Execution context
     * @param array<string, mixed>|null      $variables     Variable values
     * @param string|null                    $operationName Operation name for multi-op documents
     * @param array<\GraphQL\Validator\Rules\ValidationRule> $validationRules Additional validation rules
     *
     * @return array{data?: array<string, mixed>|null, errors?: array<array<string, mixed>>}
     */
    public function execute(
        Schema $schema,
        string $query,
        GraphQLContext $context,
        ?array $variables = null,
        ?string $operationName = null,
        array $validationRules = [],
    ): array {
        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            rootValue: null,
            contextValue: $context,
            variableValues: $variables,
            operationName: $operationName,
            validationRules: $validationRules !== []
                ? array_merge(DocumentValidator::defaultRules(), $validationRules)
                : null,
        );

        $result->setErrorFormatter($this->errorHandler->formatter());
        $result->setErrorsHandler($this->errorHandler->handler());

        return $result->toArray();
    }

    /**
     * Execute a query and return the raw ExecutionResult.
     *
     * @param Schema                         $schema        The GraphQL schema
     * @param string                         $query         The query string
     * @param GraphQLContext                 $context       Execution context
     * @param array<string, mixed>|null      $variables     Variable values
     * @param string|null                    $operationName Operation name
     * @param array<\GraphQL\Validator\Rules\ValidationRule> $validationRules Validation rules
     *
     * @return \GraphQL\Executor\ExecutionResult
     */
    public function executeRaw(
        Schema $schema,
        string $query,
        GraphQLContext $context,
        ?array $variables = null,
        ?string $operationName = null,
        array $validationRules = [],
    ): \GraphQL\Executor\ExecutionResult {
        return GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            rootValue: null,
            contextValue: $context,
            variableValues: $variables,
            operationName: $operationName,
            validationRules: $validationRules !== []
                ? array_merge(DocumentValidator::defaultRules(), $validationRules)
                : null,
        );
    }
}
