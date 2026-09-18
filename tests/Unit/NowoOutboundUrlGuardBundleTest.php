<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Unit;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\NowoOutboundUrlGuardBundle;
use PHPUnit\Framework\TestCase;

final class NowoOutboundUrlGuardBundleTest extends TestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new NowoOutboundUrlGuardBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(NowoOutboundUrlGuardExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }
}
