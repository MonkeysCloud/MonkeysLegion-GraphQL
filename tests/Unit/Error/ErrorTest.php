<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Error;

use MonkeysLegion\GraphQL\Error\AuthorizationError;
use MonkeysLegion\GraphQL\Error\ValidationError;
use PHPUnit\Framework\TestCase;

final class ErrorTest extends TestCase
{
    public function testAuthorizationErrorIsClientSafe(): void
    {
        $error = new AuthorizationError('Forbidden');
        $this->assertTrue($error->isClientSafe());
        $this->assertSame('Forbidden', $error->getMessage());
    }

    public function testAuthorizationErrorDefaultMessage(): void
    {
        $error = new AuthorizationError();
        $this->assertSame('Unauthorized', $error->getMessage());
    }

    public function testValidationErrorIsClientSafe(): void
    {
        $error = new ValidationError('Validation failed', ['name' => ['required']]);
        $this->assertTrue($error->isClientSafe());
        $this->assertSame('Validation failed', $error->getMessage());
        $this->assertSame(['name' => ['required']], $error->getValidationErrors());
    }

    public function testValidationErrorDefaultMessage(): void
    {
        $error = new ValidationError();
        $this->assertSame('Validation failed', $error->getMessage());
        $this->assertSame([], $error->getValidationErrors());
    }
}
