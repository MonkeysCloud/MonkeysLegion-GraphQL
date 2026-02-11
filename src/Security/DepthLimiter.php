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
 * Validation rule that limits the depth of GraphQL queries.
 *
 * Walks the query AST and tracks nesting depth, throwing a validation
 * error if the maximum allowed depth is exceeded.
 */
final class DepthLimiter extends ValidationRule
{
    /**
     * @param int $maxDepth Maximum allowed query depth (0 = unlimited)
     */
    public function __construct(
        private readonly int $maxDepth,
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
        if ($this->maxDepth <= 0) {
            return [];
        }

        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function (OperationDefinitionNode $node) use ($context): void {
                    $depth = $this->measureDepth($node->selectionSet, $context, 0);

                    if ($depth > $this->maxDepth) {
                        $context->reportError(new Error(
                            sprintf(
                                'Query depth of %d exceeds maximum allowed depth of %d.',
                                $depth,
                                $this->maxDepth,
                            ),
                        ));
                    }
                },
            ],
        ];
    }

    /**
     * Recursively measure the depth of a selection set.
     *
     * @param SelectionSetNode|null  $selectionSet The selection set to measure
     * @param QueryValidationContext $context      Validation context
     * @param int                    $currentDepth Current depth level
     *
     * @return int The maximum depth found
     */
    private function measureDepth(
        ?SelectionSetNode $selectionSet,
        QueryValidationContext $context,
        int $currentDepth,
    ): int {
        if ($selectionSet === null) {
            return $currentDepth;
        }

        $maxDepth = $currentDepth;

        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                // Skip introspection fields
                if (str_starts_with($selection->name->value, '__')) {
                    continue;
                }

                $fieldDepth = $this->measureDepth(
                    $selection->selectionSet,
                    $context,
                    $currentDepth + 1,
                );
                $maxDepth = max($maxDepth, $fieldDepth);
            } elseif ($selection instanceof InlineFragmentNode) {
                $fragmentDepth = $this->measureDepth(
                    $selection->selectionSet,
                    $context,
                    $currentDepth,
                );
                $maxDepth = max($maxDepth, $fragmentDepth);
            } elseif ($selection instanceof FragmentSpreadNode) {
                $fragment = $context->getFragment($selection->name->value);
                if ($fragment !== null) {
                    $fragmentDepth = $this->measureDepth(
                        $fragment->selectionSet,
                        $context,
                        $currentDepth,
                    );
                    $maxDepth = max($maxDepth, $fragmentDepth);
                }
            }
        }

        return $maxDepth;
    }
}
