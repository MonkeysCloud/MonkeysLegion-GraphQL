<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Type;

use GraphQL\Error\Error;
use MonkeysLegion\GraphQL\Type\DateTimeScalar;
use MonkeysLegion\GraphQL\Type\EmailScalar;
use MonkeysLegion\GraphQL\Type\JsonScalar;
use MonkeysLegion\GraphQL\Type\UrlScalar;
use PHPUnit\Framework\TestCase;

final class ScalarTest extends TestCase
{
    // --- DateTimeScalar ---

    public function testDateTimeSerializesDateTime(): void
    {
        $scalar = new DateTimeScalar();
        $dt = new \DateTimeImmutable('2026-01-15T10:30:00+00:00');
        $result = $scalar->serialize($dt);
        self::assertSame('2026-01-15T10:30:00+00:00', $result);
    }

    public function testDateTimeSerializesString(): void
    {
        $scalar = new DateTimeScalar();
        $result = $scalar->serialize('2026-01-15T10:30:00+00:00');
        self::assertSame('2026-01-15T10:30:00+00:00', $result);
    }

    public function testDateTimeParseValueValid(): void
    {
        $scalar = new DateTimeScalar();
        $result = $scalar->parseValue('2026-01-15T10:30:00+00:00');
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function testDateTimeParseValueInvalid(): void
    {
        $this->expectException(Error::class);
        $scalar = new DateTimeScalar();
        $scalar->parseValue(12345); // Non-string triggers error
    }

    // --- EmailScalar ---

    public function testEmailSerializesString(): void
    {
        $scalar = new EmailScalar();
        self::assertSame('test@example.com', $scalar->serialize('test@example.com'));
    }

    public function testEmailParseValueValid(): void
    {
        $scalar = new EmailScalar();
        self::assertSame('user@test.com', $scalar->parseValue('user@test.com'));
    }

    public function testEmailParseValueInvalid(): void
    {
        $this->expectException(Error::class);
        $scalar = new EmailScalar();
        $scalar->parseValue('not-an-email');
    }

    // --- UrlScalar ---

    public function testUrlSerializesString(): void
    {
        $scalar = new UrlScalar();
        self::assertSame('https://example.com', $scalar->serialize('https://example.com'));
    }

    public function testUrlParseValueValid(): void
    {
        $scalar = new UrlScalar();
        self::assertSame('https://example.com/path', $scalar->parseValue('https://example.com/path'));
    }

    public function testUrlParseValueInvalid(): void
    {
        $this->expectException(Error::class);
        $scalar = new UrlScalar();
        $scalar->parseValue('not a url');
    }

    // --- JsonScalar ---

    public function testJsonSerializesArray(): void
    {
        $scalar = new JsonScalar();
        $data = ['key' => 'value', 'numbers' => [1, 2, 3]];
        self::assertSame($data, $scalar->serialize($data));
    }

    public function testJsonParseValueValidString(): void
    {
        $scalar = new JsonScalar();
        $result = $scalar->parseValue('{"key": "value"}');
        self::assertIsArray($result);
        self::assertSame('value', $result['key']);
    }

    public function testJsonParseValueArray(): void
    {
        $scalar = new JsonScalar();
        $input = ['already' => 'parsed'];
        self::assertSame($input, $scalar->parseValue($input));
    }

    public function testJsonParseValueInvalid(): void
    {
        $this->expectException(\JsonException::class);
        $scalar = new JsonScalar();
        $scalar->parseValue('{invalid json');
    }
}
