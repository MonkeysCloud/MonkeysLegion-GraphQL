<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Upload;

use GraphQL\Error\Error;
use MonkeysLegion\GraphQL\Upload\UploadType;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class UploadTypeTest extends TestCase
{
    private UploadType $type;

    protected function setUp(): void
    {
        $this->type = new UploadType();
    }

    public function testName(): void
    {
        self::assertSame('Upload', $this->type->name);
    }

    public function testSerializeThrows(): void
    {
        $this->expectException(Error::class);
        $this->type->serialize('anything');
    }

    public function testParseValueAcceptsUploadedFile(): void
    {
        $file = new UploadedFile('content', 7, UPLOAD_ERR_OK, 'test.txt', 'text/plain');
        $result = $this->type->parseValue($file);
        self::assertSame($file, $result);
    }

    public function testParseValueRejectsNonFile(): void
    {
        $this->expectException(Error::class);
        $this->type->parseValue('not a file');
    }
}
