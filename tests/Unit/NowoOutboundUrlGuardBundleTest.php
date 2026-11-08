<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Unit;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\Compiler\DnsLookupClassPass;
use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\NowoOutboundUrlGuardBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_filter;

final class NowoOutboundUrlGuardBundleTest extends TestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new NowoOutboundUrlGuardBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(NowoOutboundUrlGuardExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }

    public function testBuildRegistersTheDnsLookupClassPass(): void
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardBundle())->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof DnsLookupClassPass);
        self::assertCount(1, $passes);
    }
}
