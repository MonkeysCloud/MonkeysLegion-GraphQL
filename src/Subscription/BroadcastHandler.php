<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Subscription;

use MonkeysLegion\GraphQL\Attribute\Broadcast;
use MonkeysLegion\GraphQL\Context\GraphQLContext;
use ReflectionClass;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Intercepts mutation resolvers annotated with #[Broadcast] and
 * automatically publishes the mutation result to the PubSub channel
 * after successful execution.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class BroadcastHandler
{
    public function __construct(
        private readonly PubSubInterface $pubSub,
    ) {}

    /**
     * Wrap a resolver with broadcast capability.
     *
     * Reads #[Broadcast] attributes from the entity class and wraps the
     * resolver so that after successful execution, the result is published
     * to the configured PubSub channel.
     *
     * @param callable     $resolver    The original resolver
     * @param class-string $entityClass The entity class (for reading attributes)
     * @param string       $operation   The CRUD operation name (e.g. 'create', 'update', 'delete')
     *
     * @return callable
     */
    public function wrapCrud(callable $resolver, string $entityClass, string $operation): callable
    {
        $reflection = new ReflectionClass($entityClass);
        $broadcastAttrs = $reflection->getAttributes(Broadcast::class);

        if ($broadcastAttrs === []) {
            return $resolver;
        }

        $pubSub = $this->pubSub;

        return static function (mixed $root, array $args, GraphQLContext $ctx, \GraphQL\Type\Definition\ResolveInfo $info) use ($resolver, $broadcastAttrs, $operation, $entityClass, $pubSub) {
            $result = $resolver($root, $args, $ctx, $info);

            $shortName = (new ReflectionClass($entityClass))->getShortName();

            foreach ($broadcastAttrs as $attrRef) {
                /** @var Broadcast $attr */
                $attr = $attrRef->newInstance();
                $eventName = $attr->event ?? "{$operation}{$shortName}";

                $payload = [
                    'event'     => $eventName,
                    'operation' => $operation,
                    'entity'    => $shortName,
                    'data'      => $result,
                    'timestamp' => time(),
                ];

                $pubSub->publish($attr->channel, $payload);
            }

            return $result;
        };
    }

    /**
     * Wrap a custom resolver method with broadcast capability.
     *
     * @param callable          $resolver The original resolver
     * @param list<Broadcast>   $broadcasts Broadcast attributes from the method
     *
     * @return callable
     */
    public function wrap(callable $resolver, array $broadcasts): callable
    {
        if ($broadcasts === []) {
            return $resolver;
        }

        $pubSub = $this->pubSub;

        return static function (mixed $root, array $args, GraphQLContext $ctx, \GraphQL\Type\Definition\ResolveInfo $info) use ($resolver, $broadcasts, $pubSub) {
            $result = $resolver($root, $args, $ctx, $info);

            foreach ($broadcasts as $attr) {
                $payload = [
                    'event'     => $attr->event ?? $info->fieldName,
                    'data'      => $result,
                    'timestamp' => time(),
                ];

                $pubSub->publish($attr->channel, $payload);
            }

            return $result;
        };
    }
}
