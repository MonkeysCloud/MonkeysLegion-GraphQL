<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\GraphQL\Cache\SchemaCache;

/**
 * Clears the GraphQL schema cache.
 *
 * Usage:
 *   php ml graphql:cache:clear
 *   php ml graphql:cache:clear --key=default
 */
#[CommandAttr('graphql:cache:clear', 'Clear the GraphQL schema cache')]
final class CacheClearCommand extends Command
{
    public function __construct(
        private readonly ?SchemaCache $schemaCache = null,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        if ($this->schemaCache === null) {
            $this->error('Schema cache is not available.');
            $this->error('Install monkeyscloud/monkeyslegion-cache and configure PSR-16 cache.');
            return self::FAILURE;
        }

        try {
            $key = $this->option('key', 'default');
            $cacheKey = is_string($key) ? $key : 'default';

            $this->info("Clearing GraphQL schema cache (key: {$cacheKey})...");

            $this->schemaCache->clear($cacheKey);

            $this->info('✅  Schema cache cleared');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to clear schema cache: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
