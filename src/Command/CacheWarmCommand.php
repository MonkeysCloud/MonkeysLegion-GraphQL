<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\GraphQL\Cache\SchemaCacheWarmer;

/**
 * Warms the GraphQL schema cache.
 *
 * Usage:
 *   php ml graphql:cache:warm
 *   php ml graphql:cache:warm --key=default
 */
#[CommandAttr('graphql:cache:warm', 'Warm the GraphQL schema cache')]
final class CacheWarmCommand extends Command
{
    public function __construct(
        private readonly ?SchemaCacheWarmer $warmer = null,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        if ($this->warmer === null) {
            $this->error('Schema cache warmer is not available.');
            $this->error('Install monkeyscloud/monkeyslegion-cache and configure PSR-16 cache.');
            return self::FAILURE;
        }

        try {
            $key = $this->option('key', 'default');
            $cacheKey = is_string($key) ? $key : 'default';

            $this->info("Warming GraphQL schema cache (key: {$cacheKey})...");

            $this->warmer->warm($cacheKey);

            $this->info('✅  Schema cache warmed successfully');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to warm schema cache: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
