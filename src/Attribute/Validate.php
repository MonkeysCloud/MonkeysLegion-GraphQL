<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Declares validation rules for a mutation input field. When placed on
 * an entity property alongside #[Fillable], these rules are automatically
 * enforced before mutation resolvers execute.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Validate
{
    /** @var list<string> */
    public readonly array $rules;

    /**
     * @param string|list<string> $rules Validation rules (e.g. 'required', 'email', 'min:3')
     */
    public function __construct(string|array $rules)
    {
        if (is_string($rules)) {
            $this->rules = explode('|', $rules);
        } else {
            $this->rules = $rules;
        }
    }
}
