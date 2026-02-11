<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

/**
 * JSON custom scalar type.
 *
 * Passes through arbitrary JSON objects/arrays. Useful for unstructured data.
 */
final class JsonScalar extends ScalarType
{
    /** @var string */
    public string $name = 'JSON';

    /** @var string|null */
    public ?string $description = 'Arbitrary JSON value (object, array, string, number, or boolean).';

    /**
     * Serialize a value for the response — pass through as-is.
     *
     * @param mixed $value The value to serialize
     *
     * @return mixed
     */
    public function serialize($value): mixed
    {
        return $value;
    }

    /**
     * Parse a value from input.
     *
     * Accepts arrays/objects directly or JSON strings.
     *
     * @param mixed $value The value to parse
     *
     * @return mixed
     *
     * @throws Error If a JSON string is malformed
     */
    public function parseValue($value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Error('Invalid JSON string: ' . json_last_error_msg());
            }
            return $decoded;
        }

        return $value;
    }

    /**
     * Parse a literal AST node into a PHP value.
     *
     * @param Node                       $valueNode The AST node
     * @param array<string, mixed>|null  $variables Variable values
     *
     * @return mixed
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        return $this->parseAstNode($valueNode);
    }

    /**
     * Recursively convert an AST node to a PHP value.
     *
     * @param Node $node The AST node
     *
     * @return mixed
     */
    private function parseAstNode(Node $node): mixed
    {
        if ($node instanceof ObjectValueNode) {
            $result = [];
            foreach ($node->fields as $field) {
                $result[$field->name->value] = $this->parseAstNode($field->value);
            }
            return $result;
        }

        if ($node instanceof ListValueNode) {
            return array_map(
                fn(Node $item) => $this->parseAstNode($item),
                iterator_to_array($node->values),
            );
        }

        if ($node instanceof StringValueNode) {
            return $node->value;
        }

        if ($node instanceof IntValueNode) {
            return (int) $node->value;
        }

        if ($node instanceof FloatValueNode) {
            return (float) $node->value;
        }

        if ($node instanceof BooleanValueNode) {
            return $node->value;
        }

        return null;
    }
}
