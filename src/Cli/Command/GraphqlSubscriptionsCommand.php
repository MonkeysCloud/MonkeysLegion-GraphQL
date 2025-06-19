<?php
declare(strict_types=1);

namespace MonkeysLegion\Cli\Command;

use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\GraphQL\WebSocket\SubscriptionServer;
use MonkeysLegion\GraphQL\Subscription\{
    SubscriptionManager,
    PubSubInterface
};

#[CommandAttr(
    'graphql:subscriptions',
    <<<TXT
Start the GraphQL WebSocket subscription server.

Usage:
  php vendor/bin/ml graphql:subscriptions [host] [port]

Examples:
  # bind 0.0.0.0:6001 (default)
  php vendor/bin/ml graphql:subscriptions

  # bind 127.0.0.1:7000
  php vendor/bin/ml graphql:subscriptions 127.0.0.1 7000
TXT
)]
final class GraphqlSubscriptionsCommand extends Command
{
    public function __construct(
        private SubscriptionManager $manager,
        private PubSubInterface     $pubsub
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        // PHP gives us the raw argv array
        $argv = $_SERVER['argv'] ?? [];

        // First element is the script, second is command name → skip both
        $host = $argv[2] ?? '0.0.0.0';
        $port = isset($argv[3]) ? (int) $argv[3] : 6001;

        $this->info("Booting WS server on ws://{$host}:{$port}");
        SubscriptionServer::run($this->manager, $this->pubsub, $host, $port);

        return self::SUCCESS;
    }
}