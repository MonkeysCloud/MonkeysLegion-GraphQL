<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Scanner;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Scanner\FilterScanner;
use MonkeysLegion\GraphQL\Tests\Fixtures\Entity\Product;

class FilterScannerTest extends TestCase
{
    public function testFilterScannerGeneratesInputTypes(): void
    {
        $scanner = new FilterScanner();
        $config = $scanner->map(Product::class);

        $this->assertNotNull($config['where']);
        $this->assertSame('ProductWhereInput', $config['where']->name);
        
        $fields = $config['where']->getFields();
        $this->assertArrayHasKey('status', $fields);
        $this->assertArrayHasKey('status_in', $fields);
        $this->assertArrayHasKey('price', $fields);
        $this->assertArrayHasKey('price_gt', $fields);
        
        $this->assertNotNull($config['orderBy']);
        $this->assertTrue($config['search']);
    }
}
