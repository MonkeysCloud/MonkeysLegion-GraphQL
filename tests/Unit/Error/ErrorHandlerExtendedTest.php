<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Error;

use GraphQL\Error\Error;
use MonkeysLegion\GraphQL\Error\AuthorizationError;
use MonkeysLegion\GraphQL\Error\ErrorHandler;
use MonkeysLegion\GraphQL\Error\ValidationError;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerExtendedTest extends TestCase
{
    public function testFormatClientAwareError(): void
    {
        $handler = new ErrorHandler(debug: false);

        $previous = new AuthorizationError('Forbidden');
        $error = new Error('wrapped', previous: $previous);

        $formatted = $handler->format($error);

        $this->assertSame('Forbidden', $formatted['message']);
        $this->assertSame('authorization', $formatted['extensions']['category']);
    }

    public function testFormatValidationErrorIncludesFieldErrors(): void
    {
        $handler = new ErrorHandler(debug: false);

        $previous = new ValidationError('Validation failed', ['email' => ['required']]);
        $error = new Error('wrapped', previous: $previous);

        $formatted = $handler->format($error);

        $this->assertSame('Validation failed', $formatted['message']);
        $this->assertSame('validation', $formatted['extensions']['category']);
        $this->assertSame(['email' => ['required']], $formatted['extensions']['validation']);
    }

    public function testFormatProductionHidesInternalErrors(): void
    {
        $handler = new ErrorHandler(debug: false);

        $previous = new \RuntimeException('database connection failed');
        $error = new Error('wrapped', previous: $previous);

        $formatted = $handler->format($error);

        $this->assertSame('Internal server error', $formatted['message']);
        $this->assertSame('internal', $formatted['extensions']['category']);
        $this->assertArrayNotHasKey('trace', $formatted['extensions']);
    }

    public function testFormatDebugIncludesTrace(): void
    {
        $handler = new ErrorHandler(debug: true);

        $previous = new \RuntimeException('something broke');
        $error = new Error('wrapped', previous: $previous);

        $formatted = $handler->format($error);

        $this->assertSame('something broke', $formatted['extensions']['debugMessage']);
        $this->assertArrayHasKey('trace', $formatted['extensions']);
        $this->assertSame('internal', $formatted['extensions']['category']);
    }

    public function testFormatNoPreviousError(): void
    {
        $handler = new ErrorHandler(debug: false);
        $error = new Error('simple error');

        $formatted = $handler->format($error);
        $this->assertStringContainsString('simple error', $formatted['message']);
    }

    public function testFormatterReturnsCallable(): void
    {
        $handler = new ErrorHandler();
        $formatter = $handler->formatter();
        $this->assertIsCallable($formatter);
    }

    public function testHandlerReturnsCallable(): void
    {
        $handler = new ErrorHandler();
        $fn = $handler->handler();
        $this->assertIsCallable($fn);

        $errors = [new Error('test')];
        $result = $fn($errors, fn() => []);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('message', $result[0]);
    }
}
