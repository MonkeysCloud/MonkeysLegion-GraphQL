<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

/**
 * Email custom scalar type.
 *
 * Validates email format on input, passes through on output.
 */
final class EmailScalar extends ScalarType
{
    /** @var string */
    public string $name = 'Email';

    /** @var string|null */
    public ?string $description = 'A valid email address string.';

    /**
     * Serialize a value for the response.
     *
     * @param mixed $value The value to serialize
     *
     * @return string
     *
     * @throws Error If the value is not a string
     */
    public function serialize($value): string
    {
        if (!is_string($value)) {
            throw new Error('Email cannot represent non-string value: ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * Parse a value from input and validate email format.
     *
     * @param mixed $value The value to parse
     *
     * @return string
     *
     * @throws Error If the value is not a valid email
     */
    public function parseValue($value): string
    {
        if (!is_string($value)) {
            throw new Error('Email expects a string value, got: ' . get_debug_type($value));
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new Error('Invalid email address: ' . $value);
        }

        return $value;
    }

    /**
     * Parse a literal AST node.
     *
     * @param Node                       $valueNode The AST node
     * @param array<string, mixed>|null  $variables Variable values
     *
     * @return string
     *
     * @throws Error If the node is invalid
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Email literal must be a string, got: ' . $valueNode::class);
        }

        if (filter_var($valueNode->value, FILTER_VALIDATE_EMAIL) === false) {
            throw new Error('Invalid email address literal: ' . $valueNode->value);
        }

        return $valueNode->value;
    }
}
