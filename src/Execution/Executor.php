<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Execution;

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Executor class to handle GraphQL query execution.
 *
 * This class is responsible for executing GraphQL queries against a defined schema.
 * It processes the incoming request, extracts the query, variables, and operation name,
 * and returns the result in a structured format.
 */
final class Executor
{

    /**
     * Constructor to initialize the Executor with a GraphQL schema.
     *
     * @param Schema $schema The GraphQL schema against which queries will be executed.
     */
    public function __construct(private Schema $schema) {}

    /** @return array{data?:mixed,errors?:array} */
    public function execute(ServerRequestInterface $r): array
    {
        $payload = json_decode((string) $r->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return GraphQL::executeQuery(
            $this->schema,
            $payload['query']   ?? '',
            null,
            ['request' => $r],        // context
            $payload['variables'] ?? null,
            $payload['operationName'] ?? null
        )->toArray();
    }
}