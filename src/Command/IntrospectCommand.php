<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use GraphQL\Utils\Introspection;

/**
 * Dumps the full introspection result as JSON.
 *
 * Usage:
 *   php ml graphql:introspect
 *   php ml graphql:introspect --output=introspection.json
 */
#[CommandAttr('graphql:introspect', 'Dump GraphQL introspection result as JSON')]
final class IntrospectCommand extends Command
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
            $this->info('Running introspection query...');

            $schema = $this->schemaBuilder->build($this->config->scanDirectories());
            $introspection = Introspection::fromSchema($schema);

            $json = json_encode($introspection, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $output = $this->option('output');

            if (is_string($output) && $output !== '') {
                file_put_contents($output, $json);
                $this->info("✅  Introspection written to {$output}");
            } else {
                $this->line('');
                $this->line($json);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to run introspection: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
