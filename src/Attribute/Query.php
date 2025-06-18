<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Query
{
    public function __construct(public ?string $name = null) {}
}