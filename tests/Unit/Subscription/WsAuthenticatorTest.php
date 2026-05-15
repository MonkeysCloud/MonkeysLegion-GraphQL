<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Subscription;

use MonkeysLegion\GraphQL\Subscription\WsAuthenticator;
use PHPUnit\Framework\TestCase;

final class WsAuthenticatorTest extends TestCase
{
    public function testAnonymousConnectionReturnsNull(): void
    {
        $auth = new WsAuthenticator();
        $result = $auth->authenticate([]);
        $this->assertNull($result);
    }

    public function testTokenWithoutHandlerThrows(): void
    {
        $auth = new WsAuthenticator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no authHandler is configured');

        $auth->authenticate(['token' => 'abc123']);
    }

    public function testAuthorizationHeaderWithoutHandlerThrows(): void
    {
        $auth = new WsAuthenticator();

        $this->expectException(\RuntimeException::class);
        $auth->authenticate(['Authorization' => 'Bearer token']);
    }

    public function testCustomHandlerReturnsUser(): void
    {
        $user = (object) ['id' => 1, 'name' => 'Alice'];

        $auth = new WsAuthenticator(function (array $payload) use ($user) {
            return isset($payload['token']) ? $user : null;
        });

        $result = $auth->authenticate(['token' => 'valid']);
        $this->assertSame($user, $result);
    }

    public function testCustomHandlerReturnsNullForInvalid(): void
    {
        $auth = new WsAuthenticator(function (array $payload) {
            return null; // All tokens invalid
        });

        $result = $auth->authenticate(['token' => 'bad']);
        $this->assertNull($result);
    }

    public function testSetHandler(): void
    {
        $auth = new WsAuthenticator();
        $user = (object) ['id' => 2];

        $auth->setHandler(fn(array $payload) => $user);

        $result = $auth->authenticate(['token' => 'abc']);
        $this->assertSame($user, $result);
    }
}
