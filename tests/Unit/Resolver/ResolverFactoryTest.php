<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Resolver;

use MonkeysLegion\GraphQL\Resolver\ResolverFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ResolverFactoryTest extends TestCase
{
    public function testCreateDelegatestoContainer(): void
    {
        $service = new \stdClass();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('MyResolver')->willReturn($service);

        $factory = new ResolverFactory($container);
        $result = $factory->create('MyResolver');

        $this->assertSame($service, $result);
    }

    public function testHasDelegatesToContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['MyResolver', true],
            ['Missing', false],
        ]);

        $factory = new ResolverFactory($container);

        $this->assertTrue($factory->has('MyResolver'));
        $this->assertFalse($factory->has('Missing'));
    }

    public function testInvokeCallsInvokeMethod(): void
    {
        $resolver = new class {
            public function __invoke(string $name): string { return "Hello {$name}"; }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($resolver);

        $factory = new ResolverFactory($container);
        $result = $factory->invoke('MyResolver', ['World']);

        $this->assertSame('Hello World', $result);
    }

    public function testInvokeThrowsIfNoInvoke(): void
    {
        $resolver = new \stdClass();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($resolver);

        $factory = new ResolverFactory($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not have an __invoke method');

        $factory->invoke('stdClass');
    }
}
