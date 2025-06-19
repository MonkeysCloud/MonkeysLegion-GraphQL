<?php
declare(strict_types=1);

namespace MonkeysLegion\Cli\Command;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\GraphQL\Schema\SchemaFactory;
use GraphQL\Utils\SchemaPrinter;

#[CommandAttr(
    'graphql:schema:print',
    'Dump the current GraphQL schema in SDL format'
)]
final class GraphqlSchemaPrintCommand extends Command
{
    public function __construct(private SchemaFactory $factory)
    {
        parent::__construct();          // keep this - your base class needs it
    }

    /**
     * @throws SerializationError
     * @throws Error
     * @throws \JsonException
     */
    protected function handle(): int
    {
        $schema = $this->factory->build();

        // graphql-php v15+  →  doPrint()
        $sdl = SchemaPrinter::doPrint($schema);

        $this->line($sdl);
        return self::SUCCESS;
    }
}