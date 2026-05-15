<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Entity;

use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Field;
use MonkeysLegion\Entity\Attributes\Id;
use MonkeysLegion\Entity\Attributes\Fillable;
use MonkeysLegion\GraphQL\Attribute\GraphQLResource;
use MonkeysLegion\GraphQL\Attribute\Filter;
use MonkeysLegion\GraphQL\Attribute\Sort;
use MonkeysLegion\GraphQL\Attribute\Search;

#[Entity(table: 'products')]
#[GraphQLResource(paginateList: true)]
class Product
{
    #[Id]
    #[Field(type: 'int', autoIncrement: true)]
    public private(set) int $id;

    #[Field(type: 'string', length: 255)]
    #[Fillable]
    #[Search]
    #[Sort]
    public string $name;

    #[Field(type: 'string', length: 50)]
    #[Fillable]
    #[Filter(operators: ['eq', 'in'])]
    public string $status;
    
    #[Field(type: 'int')]
    #[Fillable]
    #[Sort]
    #[Filter(operators: ['eq', 'gt', 'lt'])]
    public int $price;
}
