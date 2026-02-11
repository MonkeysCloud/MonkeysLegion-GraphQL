<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Security;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * Validation rule that limits the complexity of GraphQL queries.
 *
 * Each field has a cost (default 1). List fields multiply by an estimated
 * count (default 10). Total cost is summed and rejected if it exceeds the
 * configured maximum.
 */
final class ComplexityAnalyzer extends ValidationRule
{
    /** @var int Default estimated count for list fields */
    private const DEFAULT_LIST_MULTIPLIER = 10;

    /**
     * @param int                    $maxComplexity   Maximum allowed complexity (0 = unlimited)
     * @param array<string, int>     $fieldCosts      Per-field cost overrides (fieldName => cost)
     * @param int                    $defaultCost     Default cost per field
     */
    public function __construct(
        private readonly int $maxComplexity,
        private readonly array $fieldCosts = [],
        private readonly int $defaultCost = 1,
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
        if ($this->maxComplexity <= 0) {
            return [];
        }

        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function (OperationDefinitionNode $node) use ($context): void {
                    $complexity = $this->calculateComplexity($node->selectionSet, $context, 1);

                    if ($complexity > $this->maxComplexity) {
                        $context->reportError(new Error(
                            sprintf(
                                'Query complexity of %d exceeds maximum allowed complexity of %d.',
                                $complexity,
                                $this->maxComplexity,
                            ),
                        ));
                    }
                },
            ],
        ];
    }

    /**
     * Calculate the total complexity of a selection set.
     *
     * @param SelectionSetNode|null  $selectionSet The selection set
     * @param QueryValidationContext $context      Validation context
     * @param int                    $multiplier   Current list multiplier
     *
     * @return int Total complexity score
     */
    private function calculateComplexity(
        ?SelectionSetNode $selectionSet,
        QueryValidationContext $context,
        int $multiplier,
    ): int {
        if ($selectionSet === null) {
            return 0;
        }

        $complexity = 0;

        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $fieldName = $selection->name->value;

                // Skip introspection fields
                if (str_starts_with($fieldName, '__')) {
                    continue;
                }

                $fieldCost = $this->fieldCosts[$fieldName] ?? $this->defaultCost;
                $totalFieldCost = $fieldCost * $multiplier;

                // Check if this field has a selection set (it's an object/list)
                if ($selection->selectionSet !== null) {
                    // Determine multiplier for children
                    $childMultiplier = $this->hasListArguments($selection)
                        ? self::DEFAULT_LIST_MULTIPLIER
                        : 1;

                    $totalFieldCost += $this->calculateComplexity(
                        $selection->selectionSet,
                        $context,
                        $multiplier * $childMultiplier,
                    );
                }

                $complexity += $totalFieldCost;
            } elseif ($selection instanceof InlineFragmentNode) {
                $complexity += $this->calculateComplexity(
                    $selection->selectionSet,
                    $context,
                    $multiplier,
                );
            } elseif ($selection instanceof FragmentSpreadNode) {
                $fragment = $context->getFragment($selection->name->value);
                if ($fragment !== null) {
                    $complexity += $this->calculateComplexity(
                        $fragment->selectionSet,
                        $context,
                        $multiplier,
                    );
                }
            }
        }

        return $complexity;
    }

    /**
     * Check if a field node has arguments suggesting it returns a list.
     *
     * Looks for common pagination arguments: first, last, limit.
     *
     * @param FieldNode $field The field node
     *
     * @return bool
     */
    private function hasListArguments(FieldNode $field): bool
    {
        foreach ($field->arguments as $arg) {
            $name = $arg->name->value;
            if (in_array($name, ['first', 'last', 'limit', 'count', 'take'], true)) {
                return true;
            }
        }

        return false;
    }
}
