<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Automatically broadcasts a mutation result to a subscription channel.
 * Place on a mutation resolver method or on a #[GraphQLResource] class
 * to auto-broadcast CRUD events.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Broadcast
{
    /**
     * @param string      $channel   PubSub channel name (e.g. 'postCreated', 'orderUpdated')
     * @param string|null $event     Event name within the channel (defaults to mutation name)
     * @param bool        $shouldQueue Whether to dispatch via queue for async broadcasting
     */
    public function __construct(
        public readonly string $channel,
        public readonly ?string $event = null,
        public readonly bool $shouldQueue = false,
    ) {}
}
