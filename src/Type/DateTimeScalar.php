<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

/**
 * DateTime custom scalar type.
 *
 * Serializes to ISO 8601 format, parses from ISO 8601 strings.
 */
final class DateTimeScalar extends ScalarType
{
    /** @var string */
    public string $name = 'DateTime';

    /** @var string|null */
    public ?string $description = 'A date-time string in ISO 8601 format (e.g. 2024-01-15T10:30:00+00:00).';

    /**
     * Serialize a DateTime value for the response.
     *
     * @param mixed $value The internal value to serialize
     *
     * @return string ISO 8601 formatted date string
     *
     * @throws Error If the value is not a valid DateTime
     */
    public function serialize($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_string($value)) {
            $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
            if ($dt !== false) {
                return $dt->format(\DateTimeInterface::ATOM);
            }
            // Try other common ISO 8601 formats
            $dt = new \DateTimeImmutable($value);
            return $dt->format(\DateTimeInterface::ATOM);
        }

        throw new Error('DateTime cannot represent non-date value: ' . var_export($value, true));
    }

    /**
     * Parse a value from the client input.
     *
     * @param mixed $value The value to parse
     *
     * @return \DateTimeImmutable
     *
     * @throws Error If the value is not a valid ISO 8601 string
     */
    public function parseValue($value): \DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new Error('DateTime expects a string value, got: ' . get_debug_type($value));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new Error('Invalid DateTime format: ' . $value, previous: $e);
        }
    }

    /**
     * Parse a literal AST node.
     *
     * @param Node                       $valueNode The AST node
     * @param array<string, mixed>|null  $variables Variable values
     *
     * @return \DateTimeImmutable
     *
     * @throws Error If the node is not a string literal
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): \DateTimeImmutable
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('DateTime literal must be a string, got: ' . $valueNode::class);
        }

        try {
            return new \DateTimeImmutable($valueNode->value);
        } catch (\Exception $e) {
            throw new Error('Invalid DateTime literal: ' . $valueNode->value, previous: $e);
        }
    }
}
