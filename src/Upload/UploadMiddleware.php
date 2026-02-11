<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Upload;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that processes multipart GraphQL file uploads.
 *
 * Implements the GraphQL multipart request specification:
 * 1. Parses the 'operations' and 'map' fields from multipart body
 * 2. Maps uploaded files into the operations variables
 * 3. Replaces the request body with the mapped operations JSON
 *
 * @see https://github.com/jaydenseric/graphql-multipart-request-spec
 */
final class UploadMiddleware implements MiddlewareInterface
{
    /**
     * Process a multipart upload request.
     *
     * @param ServerRequestInterface  $request The incoming request
     * @param RequestHandlerInterface $handler The next handler
     *
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type');

        if (!str_contains($contentType, 'multipart/form-data')) {
            return $handler->handle($request);
        }

        $parsedBody = $request->getParsedBody();
        if (!is_array($parsedBody)) {
            return $handler->handle($request);
        }

        $operations = $parsedBody['operations'] ?? null;
        $map = $parsedBody['map'] ?? null;

        if ($operations === null || $map === null) {
            return $handler->handle($request);
        }

        if (is_string($operations)) {
            $operations = json_decode($operations, true);
        }

        if (is_string($map)) {
            $map = json_decode($map, true);
        }

        if (!is_array($operations) || !is_array($map)) {
            return $handler->handle($request);
        }

        $uploadedFiles = $request->getUploadedFiles();

        // Map files to operation variables
        foreach ($map as $fileKey => $paths) {
            if (!is_array($paths)) {
                continue;
            }

            $file = $uploadedFiles[$fileKey] ?? null;
            if ($file === null) {
                continue;
            }

            foreach ($paths as $path) {
                $operations = $this->setByPath($operations, $path, $file);
            }
        }

        // Re-encode as JSON body for the GraphQL handler
        $json = json_encode($operations, JSON_THROW_ON_ERROR);

        /** @var \Psr\Http\Message\StreamFactoryInterface|null $streamFactory */
        $streamFactory = null;

        if (class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class)) {
            $streamFactory = new \Nyholm\Psr7\Factory\Psr17Factory();
        }

        if ($streamFactory !== null) {
            $stream = $streamFactory->createStream($json);
            $request = $request
                ->withBody($stream)
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody($operations);
        } else {
            $request = $request
                ->withParsedBody($operations);
        }

        return $handler->handle($request);
    }

    /**
     * Set a value in a nested array using dot-separated path.
     *
     * @param array<string, mixed> $data  The target array
     * @param string               $path  Dot-separated path (e.g., "variables.file")
     * @param mixed                $value The value to set
     *
     * @return array<string, mixed>
     */
    private function setByPath(array $data, string $path, mixed $value): array
    {
        $keys = explode('.', $path);
        $current = &$data;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }

        return $data;
    }
}
