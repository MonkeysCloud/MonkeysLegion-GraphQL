<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Fixtures\Entity;

use MonkeysLegion\Entity\Attributes\Entity;
use MonkeysLegion\Entity\Attributes\Field;
use MonkeysLegion\Entity\Attributes\Id;
use MonkeysLegion\Entity\Attributes\Fillable;
use MonkeysLegion\GraphQL\Attribute\GraphQLResource;
use MonkeysLegion\Validation\Attributes\NotBlank;
use MonkeysLegion\Validation\Attributes\Email;

#[Entity(table: 'users')]
#[GraphQLResource]
class User
{
    #[Id]
    #[Field(type: 'int', autoIncrement: true)]
    public private(set) int $id;

    #[Field(type: 'string', length: 255)]
    #[Fillable]
    #[NotBlank]
    public string $name;

    #[Field(type: 'string', length: 255)]
    #[Fillable]
    #[NotBlank]
    #[Email]
    public string $email;
    
    #[Field(type: 'bool')]
    #[Fillable]
    public bool $is_active = true;
}
