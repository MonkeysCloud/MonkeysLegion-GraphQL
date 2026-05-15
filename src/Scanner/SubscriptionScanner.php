<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Scanner;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use MonkeysLegion\GraphQL\Attribute\Broadcast;
use MonkeysLegion\GraphQL\Attribute\GraphQLResource;
use ReflectionClass;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Scans entities annotated with #[GraphQLResource] and #[Broadcast]
 * to auto-generate GraphQL Subscription root fields.
 *
 * For each entity with a #[Broadcast] attribute, subscription fields are
 * generated matching the event channels (e.g. `onPostCreated`, `onOrderUpdated`).
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class SubscriptionScanner
{
    /**
     * Build subscription fields from annotated entities.
     *
     * @param list<class-string>                $resources    Entity classes with #[GraphQLResource]
     * @param callable(class-string): ObjectType $typeResolver Resolves entity class to GraphQL ObjectType
     *
     * @return array<string, array<string, mixed>> Subscription root fields
     */
    public function scan(array $resources, callable $typeResolver): array
    {
        $fields = [];

        foreach ($resources as $resource) {
            $reflection = new ReflectionClass($resource);
            $broadcastAttrs = $reflection->getAttributes(Broadcast::class);

            if ($broadcastAttrs === []) {
                continue;
            }

            /** @var GraphQLResource $resourceAttr */
            $resourceAttr = $reflection->getAttributes(GraphQLResource::class)[0]->newInstance();
            $shortName = $reflection->getShortName();
            $graphqlType = $typeResolver($resource);

            foreach ($broadcastAttrs as $attrRef) {
                /** @var Broadcast $attr */
                $attr = $attrRef->newInstance();
                $channel = $attr->channel;

                // Generate subscription fields for each CRUD operation that is enabled
                foreach ($resourceAttr->operations as $op) {
                    if (!in_array($op, ['create', 'update', 'delete'], true)) {
                        continue;
                    }

                    $eventName = $attr->event ?? "{$op}{$shortName}";
                    $fieldName = 'on' . ucfirst($eventName);

                    $returnType = $op === 'delete' ? Type::boolean() : $graphqlType;

                    $fields[$fieldName] = [
                        'type' => $returnType,
                        'description' => "Subscribes to {$op} events on {$shortName}.",
                        'args' => [],
                        'resolve' => static fn(mixed $payload) => $payload['data'] ?? null,
                        '_channel' => $channel,
                        '_event' => $eventName,
                    ];
                }
            }
        }

        return $fields;
    }
}
