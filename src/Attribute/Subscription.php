<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * Subscription attribute to mark a class as a GraphQL subscription.
 *
 * This attribute can be used to annotate classes that represent GraphQL subscriptions.
 * The `name` parameter can be used to specify the name of the subscription.
 *
 * @package MonkeysLegion\GraphQL\Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Subscription
{
    /**
     * Subscription constructor.
     *
     * @param string|null $name The name of the subscription. If null, the class name will be used.
     */
    public function __construct(public ?string $name = null) {}
}