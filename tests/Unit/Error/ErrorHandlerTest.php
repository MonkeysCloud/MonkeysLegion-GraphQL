<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Error;

use GraphQL\Error\Error as GraphQLError;
use MonkeysLegion\GraphQL\Error\AuthorizationError;
use MonkeysLegion\GraphQL\Error\ErrorHandler;
use MonkeysLegion\GraphQL\Error\ValidationError;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerTest extends TestCase
{
    public function testDevModeExposesInternalErrors(): void
    {
        $handler = new ErrorHandler(debug: true);

        $error = new GraphQLError(
            'Internal failure',
            previous: new \RuntimeException('DB connection lost'),
        );

        $formatted = $handler->format($error);
        self::assertArrayHasKey('extensions', $formatted);
        self::assertArrayHasKey('debugMessage', $formatted['extensions']);
        self::assertSame('DB connection lost', $formatted['extensions']['debugMessage']);
        self::assertArrayHasKey('trace', $formatted['extensions']);
    }

    public function testProductionModeHidesInternalErrors(): void
    {
        $handler = new ErrorHandler(debug: false);

        $error = new GraphQLError(
            'Internal failure',
            previous: new \RuntimeException('secret info'),
        );

        $formatted = $handler->format($error);
        self::assertSame('Internal server error', $formatted['message']);
    }

    public function testValidationErrorPassesThrough(): void
    {
        $handler = new ErrorHandler(debug: false);

        $validation = new ValidationError('Bad input', ['name' => ['required']]);
        $error = new GraphQLError('Bad input', previous: $validation);

        $formatted = $handler->format($error);
        self::assertStringContainsString('Bad input', $formatted['message']);
        self::assertSame('validation', $formatted['extensions']['category']);
        self::assertSame(['name' => ['required']], $formatted['extensions']['validation']);
    }

    public function testAuthorizationErrorPassesThrough(): void
    {
        $handler = new ErrorHandler(debug: false);

        $auth = new AuthorizationError('Not authorized');
        $error = new GraphQLError('Not authorized', previous: $auth);

        $formatted = $handler->format($error);
        self::assertStringContainsString('Not authorized', $formatted['message']);
        self::assertSame('authorization', $formatted['extensions']['category']);
    }

    public function testValidationErrorIsClientAware(): void
    {
        $error = new ValidationError('Bad input', ['email' => ['invalid format']]);
        self::assertTrue($error->isClientSafe());
        self::assertSame(['email' => ['invalid format']], $error->getValidationErrors());
    }

    public function testAuthorizationErrorIsClientAware(): void
    {
        $error = new AuthorizationError('Forbidden');
        self::assertTrue($error->isClientSafe());
        self::assertSame('Forbidden', $error->getMessage());
    }

    public function testFormatterReturnsCallable(): void
    {
        $handler = new ErrorHandler(debug: false);
        $formatter = $handler->formatter();
        self::assertIsCallable($formatter);

        $error = new GraphQLError('Test');
        $result = $formatter($error);
        self::assertIsArray($result);
        self::assertSame('Test', $result['message']);
    }
}
