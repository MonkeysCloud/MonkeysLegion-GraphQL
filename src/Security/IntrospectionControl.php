<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Security;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * Validation rule that controls access to introspection queries.
 *
 * When introspection is disabled, queries containing __schema or __type
 * fields will receive an error.
 */
final class IntrospectionControl extends ValidationRule
{
    /**
     * @param bool $allowIntrospection Whether introspection is allowed
     */
    public function __construct(
        private readonly bool $allowIntrospection = true,
    ) {}

    /**
     * Get the visitor for this validation rule.
     *
     * @param QueryValidationContext $context Validation context
     *
     * @return array<string, callable>
     */
    public function getVisitor(QueryValidationContext $context): array
    {
        if ($this->allowIntrospection) {
            return [];
        }

        return [
            NodeKind::FIELD => [
                'enter' => static function (FieldNode $node) use ($context): void {
                    $fieldName = $node->name->value;

                    if ($fieldName === '__schema' || $fieldName === '__type') {
                        $context->reportError(new Error(
                            'GraphQL introspection is disabled.',
                            [$node],
                        ));
                    }
                },
            ],
        ];
    }
}
