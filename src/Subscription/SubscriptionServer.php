<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Subscription;

/**
 * WebSocket subscription server implementing the graphql-ws protocol.
 *
 * Protocol messages:
 * - Client: connection_init → Server: connection_ack
 * - Client: subscribe → Server: next* → complete
 * - Client: complete → Server unsubscribes
 *
 * @see https://github.com/enisdenjo/graphql-ws/blob/master/PROTOCOL.md
 */
final class SubscriptionServer
{
    /** @var array<string, object|null> connectionId => authenticated user */
    private array $connections = [];

    /**
     * @param SubscriptionManager $manager       Subscription manager
     * @param WsAuthenticator     $authenticator WebSocket authenticator
     */
    public function __construct(
        private readonly SubscriptionManager $manager,
        private readonly WsAuthenticator $authenticator,
    ) {}

    /**
     * Handle an incoming WebSocket message.
     *
     * @param string   $connectionId Connection identifier
     * @param string   $message      Raw message string
     * @param callable $send         Callback to send a message back: fn(string $message): void
     *
     * @return void
     */
    public function handleMessage(string $connectionId, string $message, callable $send): void
    {
        $data = json_decode($message, true);
        if (!is_array($data) || !isset($data['type'])) {
            $send(json_encode([
                'type'    => 'error',
                'payload' => ['message' => 'Invalid message format.'],
            ], JSON_THROW_ON_ERROR));
            return;
        }

        $type = $data['type'];

        match ($type) {
            'connection_init' => $this->handleConnectionInit($connectionId, $data, $send),
            'subscribe'       => $this->handleSubscribe($connectionId, $data, $send),
            'complete'        => $this->handleComplete($connectionId, $data, $send),
            'ping'            => $send(json_encode(['type' => 'pong'], JSON_THROW_ON_ERROR)),
            default           => $send(json_encode([
                'type'    => 'error',
                'payload' => ['message' => "Unknown message type: {$type}"],
            ], JSON_THROW_ON_ERROR)),
        };
    }

    /**
     * Handle a connection being closed.
     *
     * @param string $connectionId Connection identifier
     *
     * @return void
     */
    public function handleDisconnect(string $connectionId): void
    {
        $this->manager->unsubscribeAll($connectionId);
        unset($this->connections[$connectionId]);
    }

    /**
     * Handle connection_init message.
     *
     * @param string               $connectionId Connection identifier
     * @param array<string, mixed> $data         Message data
     * @param callable             $send         Send callback
     *
     * @return void
     */
    private function handleConnectionInit(string $connectionId, array $data, callable $send): void
    {
        $payload = $data['payload'] ?? [];

        // Authenticate if payload contains credentials
        $user = null;
        if (is_array($payload) && $payload !== []) {
            $user = $this->authenticator->authenticate($payload);
        }

        $this->connections[$connectionId] = $user;

        $send(json_encode(['type' => 'connection_ack'], JSON_THROW_ON_ERROR));
    }

    /**
     * Handle subscribe message.
     *
     * @param string               $connectionId Connection identifier
     * @param array<string, mixed> $data         Message data
     * @param callable             $send         Send callback
     *
     * @return void
     */
    private function handleSubscribe(string $connectionId, array $data, callable $send): void
    {
        if (!isset($this->connections[$connectionId])) {
            $send(json_encode([
                'type'    => 'error',
                'id'      => $data['id'] ?? null,
                'payload' => ['message' => 'Connection not initialized. Send connection_init first.'],
            ], JSON_THROW_ON_ERROR));
            return;
        }

        $id = $data['id'] ?? null;
        $payload = $data['payload'] ?? [];

        if ($id === null || !is_array($payload) || !isset($payload['query'])) {
            $send(json_encode([
                'type'    => 'error',
                'id'      => $id,
                'payload' => ['message' => 'Invalid subscribe payload.'],
            ], JSON_THROW_ON_ERROR));
            return;
        }

        // Extract subscription channel from the query
        $channel = $this->extractChannel($payload['query']);

        $this->manager->subscribe(
            $connectionId,
            (string) $id,
            $channel,
            static function (mixed $eventPayload) use ($send, $id): void {
                $send(json_encode([
                    'type'    => 'next',
                    'id'      => $id,
                    'payload' => ['data' => $eventPayload],
                ], JSON_THROW_ON_ERROR));
            },
        );
    }

    /**
     * Handle complete message (client stops subscription).
     *
     * @param string               $connectionId Connection identifier
     * @param array<string, mixed> $data         Message data
     * @param callable             $send         Send callback
     *
     * @return void
     */
    private function handleComplete(string $connectionId, array $data, callable $send): void
    {
        $id = $data['id'] ?? null;

        if ($id !== null) {
            $this->manager->unsubscribe($connectionId, (string) $id);
        }
    }

    /**
     * Extract the subscription channel name from a query.
     *
     * Uses the webonyx/graphql-php parser to properly handle complex
     * GraphQL syntax (aliases, fragments, comments, etc.).
     *
     * @param string $query The GraphQL subscription query
     *
     * @return string The channel name (first subscription field)
     */
    private function extractChannel(string $query): string
    {
        try {
            $document = \GraphQL\Language\Parser::parse($query);

            foreach ($document->definitions as $definition) {
                if (!$definition instanceof \GraphQL\Language\AST\OperationDefinitionNode) {
                    continue;
                }
                if ($definition->operation !== 'subscription') {
                    continue;
                }

                foreach ($definition->selectionSet->selections as $selection) {
                    if ($selection instanceof \GraphQL\Language\AST\FieldNode) {
                        return $selection->name->value;
                    }
                }
            }
        } catch (\GraphQL\Error\SyntaxError) {
            // Fall through to default
        }

        return 'default';
    }

    /**
     * Get the authenticated user for a connection.
     *
     * @param string $connectionId Connection identifier
     *
     * @return object|null
     */
    public function getUser(string $connectionId): ?object
    {
        return $this->connections[$connectionId] ?? null;
    }

    /**
     * Check if a connection is initialized.
     *
     * @param string $connectionId Connection identifier
     *
     * @return bool
     */
    public function isConnected(string $connectionId): bool
    {
        return array_key_exists($connectionId, $this->connections);
    }
}
