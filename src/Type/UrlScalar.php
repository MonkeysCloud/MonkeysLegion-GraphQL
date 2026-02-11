<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

/**
 * URL custom scalar type.
 *
 * Validates URL format on input, passes through on output.
 */
final class UrlScalar extends ScalarType
{
    /** @var string */
    public string $name = 'URL';

    /** @var string|null */
    public ?string $description = 'A valid URL string.';

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
            throw new Error('URL cannot represent non-string value: ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * Parse a value from input and validate URL format.
     *
     * @param mixed $value The value to parse
     *
     * @return string
     *
     * @throws Error If the value is not a valid URL
     */
    public function parseValue($value): string
    {
        if (!is_string($value)) {
            throw new Error('URL expects a string value, got: ' . get_debug_type($value));
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new Error('Invalid URL: ' . $value);
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
            throw new Error('URL literal must be a string, got: ' . $valueNode::class);
        }

        if (filter_var($valueNode->value, FILTER_VALIDATE_URL) === false) {
            throw new Error('Invalid URL literal: ' . $valueNode->value);
        }

        return $valueNode->value;
    }
}
