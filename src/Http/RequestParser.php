<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Parses GraphQL operations from PSR-7 requests.
 *
 * Supports GET query parameters, POST JSON body, and multipart form data.
 * Extracts query, variables, operationName, and extensions.
 *
 * @phpstan-type ParsedRequest array{query: string|null, variables: array<string, mixed>, operationName: string|null, extensions: array<string, mixed>}
 */
final class RequestParser
{
    /**
     * Parse a GraphQL request from a PSR-7 server request.
     *
     * @param ServerRequestInterface $request The incoming HTTP request
     *
     * @return array{query: string|null, variables: array<string, mixed>, operationName: string|null, extensions: array<string, mixed>}
     */
    public function parse(ServerRequestInterface $request): array
    {
        $method = strtoupper($request->getMethod());

        return match ($method) {
            'GET'  => $this->parseGet($request),
            'POST' => $this->parsePost($request),
            default => [
                'query'         => null,
                'variables'     => [],
                'operationName' => null,
                'extensions'    => [],
            ],
        };
    }

    /**
     * Parse multiple batched operations from a request.
     *
     * @param ServerRequestInterface $request The incoming HTTP request
     *
     * @return array<array{query: string|null, variables: array<string, mixed>, operationName: string|null, extensions: array<string, mixed>}>
     */
    public function parseBatch(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();
            $decoded = json_decode($body, true);

            if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
                return array_map(
                    fn(array $op) => $this->normalizeOperation($op),
                    $decoded,
                );
            }
        }

        // Single operation fallback
        return [$this->parse($request)];
    }

    /**
     * Check if a request contains a batched operation.
     *
     * @param ServerRequestInterface $request The request to check
     *
     * @return bool
     */
    public function isBatch(ServerRequestInterface $request): bool
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'application/json')) {
            return false;
        }

        $body = (string) $request->getBody();
        $decoded = json_decode($body, true);

        return is_array($decoded) && isset($decoded[0]) && is_array($decoded[0]);
    }

    /**
     * Parse a GET request.
     *
     * @param ServerRequestInterface $request The GET request
     *
     * @return array{query: string|null, variables: array<string, mixed>, operationName: string|null, extensions: array<string, mixed>}
     */
    private function parseGet(ServerRequestInterface $request): array
    {
        $params = $request->getQueryParams();

        $variables = $params['variables'] ?? '{}';
        if (is_string($variables)) {
            $variables = json_decode($variables, true) ?? [];
        }

        $extensions = $params['extensions'] ?? '{}';
        if (is_string($extensions)) {
            $extensions = json_decode($extensions, true) ?? [];
        }

        return [
            'query'         => $params['query'] ?? null,
            'variables'     => is_array($variables) ? $variables : [],
            'operationName' => $params['operationName'] ?? null,
            'extensions'    => is_array($extensions) ? $extensions : [],
        ];
    }

    /**
     * Parse a POST request.
     *
     * @param ServerRequestInterface $request The POST request
     *
     * @return array{query: string|null, variables: array<string, mixed>, operationName: string|null, extensions: array<string, mixed>}
     */
    private function parsePost(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            return $this->parseJsonBody($request);
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            return $this->parseMultipartBody($request);
        }

        if (str_contains($contentType, 'application/graphql')) {
            return [
                'query'         => (string) $request->getBody(),
                'variables'     => [],
                'operationName' => null,
                'extensions'    => [],
            ];
        }

        return $this->parseJsonBody($request);
    }

    /**
     * Parse a JSON POST body.
     *
     * @param ServerRequestInterface $request The request
     *
     * @return array{query: string|null, variables: array<string, mixed>, operationName: string|null, extensions: array<string, mixed>}
     */
    private function parseJsonBody(ServerRequestInterface $request): array
    {
        $body = (string) $request->getBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return [
                'query'         => null,
                'variables'     => [],
                'operationName' => null,
                'extensions'    => [],
            ];
        }

        return $this->normalizeOperation($decoded);
    }

    /**
     * Parse a multipart form data body.
     *
     * @param ServerRequestInterface $request The request
     *
     * @return array{query: string|null, variables: array<string, mixed>, operationName: string|null, extensions: array<string, mixed>}
     */
    private function parseMultipartBody(ServerRequestInterface $request): array
    {
        $parsedBody = $request->getParsedBody();

        if (!is_array($parsedBody)) {
            return [
                'query'         => null,
                'variables'     => [],
                'operationName' => null,
                'extensions'    => [],
            ];
        }

        // Handle GraphQL multipart request spec
        $operations = $parsedBody['operations'] ?? null;
        if (is_string($operations)) {
            $operations = json_decode($operations, true) ?? [];
        }

        $map = $parsedBody['map'] ?? null;
        if (is_string($map)) {
            $map = json_decode($map, true);
        }

        if (is_array($operations)) {
            $data = $operations;
        } else {
            $data = $parsedBody;
        }

        $variables = $data['variables'] ?? [];
        if (is_string($variables)) {
            $variables = json_decode($variables, true) ?? [];
        }

        // Map uploaded files to variables
        if (is_array($map)) {
            $uploadedFiles = $request->getUploadedFiles();
            foreach ($map as $fileKey => $paths) {
                if (!is_array($paths)) {
                    continue;
                }
                foreach ($paths as $path) {
                    $file = $uploadedFiles[$fileKey] ?? null;
                    if ($file !== null && is_array($variables)) {
                        $variables = $this->setNestedValue($variables, $path, $file);
                    }
                }
            }
        }

        return [
            'query'         => $data['query'] ?? null,
            'variables'     => is_array($variables) ? $variables : [],
            'operationName' => $data['operationName'] ?? null,
            'extensions'    => is_array($data['extensions'] ?? null) ? $data['extensions'] : [],
        ];
    }

    /**
     * Normalize an operation array to the expected format.
     *
     * @param array<string, mixed> $data Raw operation data
     *
     * @return array{query: string|null, variables: array<string, mixed>, operationName: string|null, extensions: array<string, mixed>}
     */
    private function normalizeOperation(array $data): array
    {
        $variables = $data['variables'] ?? [];
        if (is_string($variables)) {
            $variables = json_decode($variables, true) ?? [];
        }

        $extensions = $data['extensions'] ?? [];
        if (is_string($extensions)) {
            $extensions = json_decode($extensions, true) ?? [];
        }

        return [
            'query'         => $data['query'] ?? null,
            'variables'     => is_array($variables) ? $variables : [],
            'operationName' => $data['operationName'] ?? null,
            'extensions'    => is_array($extensions) ? $extensions : [],
        ];
    }

    /**
     * Set a nested value in an array using dot-notation path.
     *
     * @param array<string, mixed> $array The target array
     * @param string               $path  Dot-notation path (e.g. "variables.file")
     * @param mixed                $value The value to set
     *
     * @return array<string, mixed>
     */
    private function setNestedValue(array $array, string $path, mixed $value): array
    {
        $keys = explode('.', $path);

        // Remove 'variables' prefix if present since we're already in variables context
        if (isset($keys[0]) && $keys[0] === 'variables') {
            array_shift($keys);
        }

        $current = &$array;
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        $current = $value;

        return $array;
    }
}
