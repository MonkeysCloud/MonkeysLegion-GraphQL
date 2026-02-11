<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use GraphQL\Type\Schema;

/**
 * Validates the GraphQL schema and reports errors.
 *
 * Usage:
 *   php ml graphql:schema:validate
 */
#[CommandAttr('graphql:schema:validate', 'Validate the GraphQL schema')]
final class SchemaValidateCommand extends Command
{
    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
        private readonly GraphQLConfig $config,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        try {
            $this->info('Validating GraphQL schema...');
            $this->line('');

            $schema = $this->schemaBuilder->build($this->config->scanDirectories());
            $schema->assertValid();

            // Count types
            $typeMap = $schema->getTypeMap();
            $userTypes = array_filter(
                $typeMap,
                static fn(mixed $type, string $name) => !str_starts_with($name, '__'),
                ARRAY_FILTER_USE_BOTH,
            );

            $queryFields = $schema->getQueryType()?->getFieldNames() ?? [];
            $mutationType = $schema->getMutationType();
            $mutationFields = $mutationType?->getFieldNames() ?? [];
            $subscriptionType = $schema->getSubscriptionType();
            $subscriptionFields = $subscriptionType?->getFieldNames() ?? [];

            $this->line('  Types:         ' . count($userTypes));
            $this->line('  Queries:       ' . count($queryFields));
            $this->line('  Mutations:     ' . count($mutationFields));
            $this->line('  Subscriptions: ' . count($subscriptionFields));
            $this->line('');
            $this->info('✅  Schema is valid');

            return self::SUCCESS;
        } catch (\GraphQL\Error\InvariantViolation $e) {
            $this->error('Schema validation failed:');
            $this->error('  ' . $e->getMessage());
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Failed to validate schema: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
