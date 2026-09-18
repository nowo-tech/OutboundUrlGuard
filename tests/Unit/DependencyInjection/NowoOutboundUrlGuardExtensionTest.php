<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Unit\DependencyInjection;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NowoOutboundUrlGuardExtensionTest extends TestCase
{
    public function testDefaultsBlockPrivateUrlsAndResolveDns(): void
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load([], $container);

        self::assertSame('nowo_outbound_url_guard', (new NowoOutboundUrlGuardExtension())->getAlias());
        self::assertFalse($container->getParameter('nowo_outbound_url_guard.allow_private'));
        self::assertTrue($container->getParameter('nowo_outbound_url_guard.resolve_dns'));
        self::assertSame(2.0, $container->getParameter('nowo_outbound_url_guard.dns_timeout'));

        $container->compile();
        $guard = $container->get(OutboundUrlGuard::class);
        self::assertInstanceOf(OutboundUrlGuard::class, $guard);
        self::assertFalse($guard->inspect('http://127.0.0.1/hook')->isValid());
    }

    public function testConfigCanAllowPrivateUrls(): void
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load([[
            'allow_private' => true,
            'resolve_dns' => false,
        ]], $container);

        self::assertTrue($container->getParameter('nowo_outbound_url_guard.allow_private'));
        self::assertFalse($container->getParameter('nowo_outbound_url_guard.resolve_dns'));

        $container->compile();
        $guard = $container->get(OutboundUrlGuard::class);
        self::assertInstanceOf(OutboundUrlGuard::class, $guard);
        self::assertTrue($guard->inspect('http://127.0.0.1/hook')->isValid());
        self::assertTrue($guard->inspect('http://mercure/hook')->isValid());
    }

    public function testRejectsUnknownConfigurationKeys(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new NowoOutboundUrlGuardExtension())->load([['not_a_key' => true]], new ContainerBuilder());
    }
}
