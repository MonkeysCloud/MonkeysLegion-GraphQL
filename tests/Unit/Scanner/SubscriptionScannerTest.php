<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Scanner;

use MonkeysLegion\GraphQL\Scanner\SubscriptionScanner;
use PHPUnit\Framework\TestCase;

final class SubscriptionScannerTest extends TestCase
{
    public function testScanWithNoBroadcastReturnsEmptyFields(): void
    {
        $scanner = new SubscriptionScanner();

        // Use stdClass which has no Broadcast attributes
        $result = $scanner->scan(
            [\stdClass::class],
            fn() => new \GraphQL\Type\Definition\ObjectType(['name' => 'Dummy', 'fields' => []])
        );

        $this->assertSame([], $result);
    }
}
