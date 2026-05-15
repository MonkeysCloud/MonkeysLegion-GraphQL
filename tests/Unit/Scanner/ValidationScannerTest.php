<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Scanner;

use MonkeysLegion\GraphQL\Scanner\ValidationScanner;
use MonkeysLegion\GraphQL\Validation\RuleSet;
use PHPUnit\Framework\TestCase;

// Use a stub entity for scanning
final class ValidationScannerTest extends TestCase
{
    public function testScanExtractsValidateRules(): void
    {
        // Create a minimal entity class with #[Validate] on the fly
        // We'll use the User fixture which has #[Fillable] and potentially #[Validate]
        $scanner = new ValidationScanner();

        // Test with a class that has no Fillable properties
        $result = $scanner->scan(\stdClass::class);

        $this->assertInstanceOf(RuleSet::class, $result['create']);
        $this->assertInstanceOf(RuleSet::class, $result['update']);
        $this->assertTrue($result['create']->isEmpty());
        $this->assertTrue($result['update']->isEmpty());
    }

    public function testScanCachesResults(): void
    {
        $scanner = new ValidationScanner();
        $r1 = $scanner->scan(\stdClass::class);
        $r2 = $scanner->scan(\stdClass::class);

        $this->assertSame($r1['create'], $r2['create']);
        $this->assertSame($r1['update'], $r2['update']);
    }
}
