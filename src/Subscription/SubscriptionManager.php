<?php
namespace MonkeysLegion\GraphQL\Subscription;

use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use React\EventLoop\LoopInterface;

final class SubscriptionManager
{
    public function __construct(
        private Schema $schema,
        private PubSubInterface $pubsub,
        private LoopInterface $loop
    ) {
        Executor::setPromiseAdapter(new ReactPromiseAdapter());
    }

    /** @return \React\Promise\PromiseInterface */
    public function subscribe(array $clientMsg, mixed $context = []): \React\Promise\PromiseInterface
    {
        return GraphQL::subscribe(
            $this->schema,
            $clientMsg['query'],
            null,
            $context + ['pubsub' => $this->pubsub],
            $clientMsg['variables'] ?? null,
            $clientMsg['operationName'] ?? null
        );
    }
}