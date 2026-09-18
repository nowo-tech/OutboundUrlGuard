<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Integration\DependencyInjection;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class GuardContainerIntegrationTest extends TestCase
{
    public function testCompiledContainerPinsPublicDnsWithConfiguredTimeout(): void
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load([[
            'dns_timeout' => 1.5,
        ]], $container);
        $container->getDefinition(HostnameDnsLookup::class)->setPublic(true);
        $container->compile();

        $lookup = $container->get(HostnameDnsLookup::class);
        $guard = $container->get(OutboundUrlGuard::class);

        self::assertInstanceOf(HostnameDnsLookup::class, $lookup);
        self::assertInstanceOf(OutboundUrlGuard::class, $guard);
        self::assertSame(1.5, $lookup->timeoutSeconds());
        self::assertFalse($guard->inspect('http://169.254.169.254/latest/meta-data', allowPrivate: true)->isValid());
    }
}
