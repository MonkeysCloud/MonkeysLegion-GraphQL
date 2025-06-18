<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\WebSocket;

use React\Promise\PromiseInterface;
use MonkeysLegion\GraphQL\Subscription\{SubscriptionManager, PubSubInterface};
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Handles WebSocket connections for GraphQL subscriptions.
 *
 * This class implements the Ratchet MessageComponentInterface to manage
 * WebSocket connections and handle incoming messages for GraphQL subscriptions.
 */
final class WsHandler implements MessageComponentInterface
{
    /** @var ConnectionInterface[] */
    private array $clients = [];

    /**
     * Constructor for the WebSocket handler.
     *
     * @param SubscriptionManager $manager The subscription manager to handle subscriptions.
     * @param PubSubInterface $pubsub The pub/sub interface for message publishing.
     */
    public function __construct(
        private SubscriptionManager $manager,
        private PubSubInterface     $pubsub
    ) {}

    /**
     * Called when a new WebSocket connection is opened.
     *
     * @param ConnectionInterface $conn The connection interface for the new connection.
     */
    public function onOpen(ConnectionInterface $conn): void {}

    /**
     * Called when a message is received from a WebSocket connection.
     *
     * @param ConnectionInterface $from The connection interface from which the message was received.
     * @param string $msg The message received from the client.
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Expecting a GraphQL subscription message as JSON:
        $payload = json_decode((string)$msg, true);
        if (!isset($payload['query'])) {
            $from->send(json_encode(['errors'=>[['message'=>'No query provided']]]));
            return;
        }

        // Start the subscription (returns a React promise)
        /** @var PromiseInterface $promise */
        $promise = $this->manager->subscribe($payload, ['pubsub' => $this->pubsub]);

        // Whenever an item arrives, send it back
        $promise->then(function($iterator) use ($from) {
            foreach ($iterator as $result) {
                $from->send(json_encode(['data' => $result]));
            }
        }, function(\Throwable $e) use ($from) {
            $from->send(json_encode(['errors'=>[['message'=>$e->getMessage()]]]));
        });
    }

    /**
     * Called when a WebSocket connection is closed.
     *
     * @param ConnectionInterface $conn The connection interface for the closed connection.
     */
    public function onClose(ConnectionInterface $conn): void {}

    /**
     * Called when an error occurs on a WebSocket connection.
     *
     * @param ConnectionInterface $conn The connection interface where the error occurred.
     * @param \Exception $e The exception that was thrown.
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        // Close on error
        $conn->close();
    }
}