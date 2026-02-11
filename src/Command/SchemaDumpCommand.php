<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\GraphQL\Builder\SchemaBuilder;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use GraphQL\Utils\SchemaPrinter;

/**
 * Dumps the GraphQL schema as SDL to stdout or a file.
 *
 * Usage:
 *   php ml graphql:schema:dump
 *   php ml graphql:schema:dump --output=schema.graphql
 */
#[CommandAttr('graphql:schema:dump', 'Dump the GraphQL schema as SDL')]
final class SchemaDumpCommand extends Command
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
            $this->info('Building GraphQL schema...');

            $schema = $this->schemaBuilder->build($this->config->scanDirectories());
            $sdl = SchemaPrinter::doPrint($schema);

            $output = $this->option('output');

            if (is_string($output) && $output !== '') {
                file_put_contents($output, $sdl);
                $this->info("✅  Schema written to {$output}");
            } else {
                $this->line('');
                $this->line($sdl);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to dump schema: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
