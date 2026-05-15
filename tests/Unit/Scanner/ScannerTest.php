<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Scanner;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\GraphQL\Scanner\ResourceScanner;
use MonkeysLegion\GraphQL\Scanner\EntityTypeMapper;
use MonkeysLegion\GraphQL\Scanner\InputScanner;
use MonkeysLegion\GraphQL\Tests\Fixtures\Entity\User;

class ScannerTest extends TestCase
{
    public function testResourceScannerFindsEntitiesWithGraphQLResource(): void
    {
        $scanner = new ResourceScanner();
        $resources = $scanner->scan([__DIR__ . '/../../Fixtures/Entity']);

        $this->assertContains(User::class, $resources);
    }

    public function testEntityTypeMapperGeneratesObjectTypeConfig(): void
    {
        $mapper = new EntityTypeMapper();
        $config = $mapper->map(User::class);

        $this->assertSame('User', $config['name']);
        $this->assertArrayHasKey('id', $config['fields']);
        $this->assertArrayHasKey('name', $config['fields']);
        $this->assertArrayHasKey('email', $config['fields']);
        $this->assertArrayHasKey('is_active', $config['fields']);
    }

    public function testInputScannerGeneratesCreateAndUpdateInputConfigs(): void
    {
        $scanner = new InputScanner();
        $config = $scanner->map(User::class);

        // Create Input
        $this->assertSame('CreateUserInput', $config['create']['name']);
        $this->assertArrayNotHasKey('id', $config['create']['fields'], 'ID should not be fillable on create');
        $this->assertArrayHasKey('name', $config['create']['fields']);
        $this->assertArrayHasKey('email', $config['create']['fields']);

        // Update Input
        $this->assertSame('UpdateUserInput', $config['update']['name']);
        $this->assertArrayHasKey('id', $config['update']['fields'], 'ID must be required for update');
        $this->assertArrayHasKey('name', $config['update']['fields']);
    }
}
