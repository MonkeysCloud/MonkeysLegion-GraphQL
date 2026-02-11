<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\GraphQL\Config\GraphQLConfig;
use MonkeysLegion\GraphQL\Subscription\SubscriptionServer;

/**
 * Starts the GraphQL WebSocket subscription server.
 *
 * The server implements the graphql-ws protocol for real-time subscriptions.
 * It uses PHP's built-in stream_socket_server for a lightweight WebSocket server.
 *
 * Usage:
 *   php ml graphql:subscriptions
 *   php ml graphql:subscriptions --host=0.0.0.0 --port=6001
 */
#[CommandAttr('graphql:subscriptions', 'Start the GraphQL WebSocket subscription server')]
final class SubscriptionServerCommand extends Command
{
    public function __construct(
        private readonly SubscriptionServer $server,
        private readonly GraphQLConfig $config,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $host = $this->option('host') ?? $this->config->subscriptionHost();
        $port = $this->option('port') ?? $this->config->subscriptionPort();

        $host = (string) $host;
        $port = (int) $port;

        if (!$this->config->subscriptionsEnabled()) {
            $this->error('Subscriptions are disabled in configuration.');
            $this->error('Set graphql.subscriptions.enabled = true in config/graphql.mlc');
            return self::FAILURE;
        }

        $this->info("🚀 Starting GraphQL WebSocket server on ws://{$host}:{$port}");
        $this->info('   Protocol: graphql-ws');
        $this->info('   Press Ctrl+C to stop.');
        $this->line('');

        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_server(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        );

        if ($socket === false) {
            $this->error("Failed to start server: [{$errno}] {$errstr}");
            return self::FAILURE;
        }

        stream_set_blocking($socket, false);

        /** @var array<int, resource> $clients */
        $clients = [];
        /** @var array<int, string> $connectionIds */
        $connectionIds = [];
        /** @var array<int, bool> $handshakeCompleted */
        $handshakeCompleted = [];

        $connectionCounter = 0;

        $this->info('✅  Server listening. Waiting for connections...');

        // @codeCoverageIgnoreStart
        while (true) {
            $read = array_merge([$socket], $clients);
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, 0, 200000) === false) {
                continue;
            }

            // Accept new connections
            if (in_array($socket, $read, true)) {
                $client = @stream_socket_accept($socket, 0);
                if ($client !== false) {
                    $clientId = (int) $client;
                    $connectionCounter++;
                    $connectionId = 'conn-' . $connectionCounter;

                    $clients[$clientId] = $client;
                    $connectionIds[$clientId] = $connectionId;
                    $handshakeCompleted[$clientId] = false;

                    stream_set_blocking($client, false);
                    $this->info("[{$connectionId}] New connection");
                }
                $read = array_diff($read, [$socket]);
            }

            // Handle client messages
            foreach ($read as $client) {
                $clientId = (int) $client;
                $connectionId = $connectionIds[$clientId] ?? 'unknown';

                $data = @fread($client, 65535);

                if ($data === false || $data === '') {
                    // Client disconnected
                    $this->info("[{$connectionId}] Disconnected");
                    $this->server->handleDisconnect($connectionId);
                    unset($clients[$clientId], $connectionIds[$clientId], $handshakeCompleted[$clientId]);
                    @fclose($client);
                    continue;
                }

                // WebSocket handshake
                if (!($handshakeCompleted[$clientId] ?? false)) {
                    $response = $this->performHandshake($data);
                    if ($response !== null) {
                        @fwrite($client, $response);
                        $handshakeCompleted[$clientId] = true;
                        $this->info("[{$connectionId}] WebSocket handshake completed");
                    } else {
                        $this->error("[{$connectionId}] Invalid handshake");
                        unset($clients[$clientId], $connectionIds[$clientId], $handshakeCompleted[$clientId]);
                        @fclose($client);
                    }
                    continue;
                }

                // Decode WebSocket frame
                $message = $this->decodeFrame($data);
                if ($message === null) {
                    continue;
                }

                $this->server->handleMessage(
                    $connectionId,
                    $message,
                    static function (string $response) use ($client): void {
                        $frame = self::encodeFrame($response);
                        @fwrite($client, $frame);
                    },
                );
            }
        }
        // @codeCoverageIgnoreEnd

        // @phpstan-ignore-next-line — loop runs until signal
        @fclose($socket);
        return self::SUCCESS;
    }

    /**
     * Perform the WebSocket upgrade handshake.
     *
     * @param string $request Raw HTTP request data
     *
     * @return string|null The handshake response, or null on failure
     */
    private function performHandshake(string $request): ?string
    {
        if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $request, $matches)) {
            return null;
        }

        $key = trim($matches[1]);
        $acceptKey = base64_encode(
            sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true),
        );

        return "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$acceptKey}\r\n"
            . "Sec-WebSocket-Protocol: graphql-transport-ws\r\n"
            . "\r\n";
    }

    /**
     * Decode a WebSocket frame to extract the payload.
     *
     * @param string $data Raw frame data
     *
     * @return string|null Decoded message or null if invalid/control frame
     */
    private function decodeFrame(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);
        $opcode = $firstByte & 0x0F;

        // Connection close
        if ($opcode === 0x08) {
            return null;
        }

        // Ping → handled at protocol level
        if ($opcode === 0x09) {
            return null;
        }

        // Only handle text frames
        if ($opcode !== 0x01) {
            return null;
        }

        $masked = ($secondByte & 0x80) !== 0;
        $length = $secondByte & 0x7F;
        $offset = 2;

        if ($length === 126) {
            if (strlen($data) < 4) {
                return null;
            }
            $length = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            if (strlen($data) < 10) {
                return null;
            }
            $length = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }

        $mask = '';
        if ($masked) {
            if (strlen($data) < $offset + 4) {
                return null;
            }
            $mask = substr($data, $offset, 4);
            $offset += 4;
        }

        if (strlen($data) < $offset + $length) {
            return null;
        }

        $payload = substr($data, $offset, $length);

        if ($masked) {
            $decoded = '';
            for ($i = 0; $i < $length; $i++) {
                $decoded .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
            }
            return $decoded;
        }

        return $payload;
    }

    /**
     * Encode a message into a WebSocket text frame.
     *
     * @param string $message The message to encode
     *
     * @return string The encoded frame
     */
    private static function encodeFrame(string $message): string
    {
        $length = strlen($message);
        $frame = chr(0x81); // FIN + text opcode

        if ($length < 126) {
            $frame .= chr($length);
        } elseif ($length < 65536) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }

        return $frame . $message;
    }
}
