<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\WebSocket;

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use MonkeysLegion\GraphQL\Subscription\{
    SubscriptionManager,
    PubSubInterface
};

/**
 * SubscriptionServer is responsible for running the WebSocket server
 * that handles GraphQL subscriptions.
 *
 * It uses Ratchet for WebSocket handling and ReactPHP for the event loop.
 */
final class SubscriptionServer
{

    /**
     * Runs the WebSocket server for GraphQL subscriptions.
     *
     * @param SubscriptionManager $manager The subscription manager to handle subscriptions.
     * @param PubSubInterface $pubsub The pub/sub interface for message broadcasting.
     * @param string $host The host address to bind the server.
     * @param int $port The port number to listen on.
     */
    public static function run(
        SubscriptionManager $manager,
        PubSubInterface      $pubsub,
        string               $host = '0.0.0.0',
        int                  $port = 6001
    ): void {
        $loop = Factory::create();

        $wsLayer = new WsServer(
            new WsHandler($manager, $pubsub)
        );

        $server = IoServer::factory(
            new HttpServer($wsLayer),
            $port,
            $host,
            $loop
        );

        echo "🔌 Subscriptions listening on ws://{$host}:{$port}\n";
        $server->run();
    }
}