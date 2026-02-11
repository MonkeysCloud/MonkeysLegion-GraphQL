<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Upload;

use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\Node;
use GraphQL\Error\Error;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Upload custom scalar type for GraphQL file uploads.
 *
 * Represents an uploaded file in GraphQL mutations, per the
 * GraphQL multipart request specification.
 */
final class UploadType extends ScalarType
{
    /** @var string */
    public string $name = 'Upload';

    /** @var string|null */
    public ?string $description = 'A file upload. Sent as multipart form data.';

    /**
     * Serialize is not applicable for uploads (output only).
     *
     * @param mixed $value The value to serialize
     *
     * @return never
     *
     * @throws Error Always throws — uploads cannot be serialized
     */
    public function serialize($value): never
    {
        throw new Error('Upload scalar cannot be serialized. It is input-only.');
    }

    /**
     * Parse an uploaded file value.
     *
     * @param mixed $value The value to parse (should be UploadedFileInterface)
     *
     * @return UploadedFileInterface
     *
     * @throws Error If the value is not an uploaded file
     */
    public function parseValue($value): UploadedFileInterface
    {
        if (!$value instanceof UploadedFileInterface) {
            throw new Error('Upload expects a file upload, got: ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * Literals are not supported for uploads.
     *
     * @param Node                       $valueNode The AST node
     * @param array<string, mixed>|null  $variables Variable values
     *
     * @return never
     *
     * @throws Error Always throws — uploads cannot be represented as literals
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): never
    {
        throw new Error('Upload scalar cannot be used in query literals. Use variables with multipart form data.');
    }
}
