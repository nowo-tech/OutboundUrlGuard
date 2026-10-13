<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Unit\Guard;

use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use Nowo\OutboundUrlGuardBundle\Exception\UnsafeOutboundUrlException;
use Nowo\OutboundUrlGuardBundle\Guard\AllowPrivateResolverInterface;
use Nowo\OutboundUrlGuardBundle\Guard\ConfiguredAllowPrivateResolver;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuardFactory;
use Nowo\OutboundUrlGuardBundle\Guard\RuntimeOutboundUrlGuard;
use PHPUnit\Framework\TestCase;

use const DNS_A;

final class RuntimeOutboundUrlGuardTest extends TestCase
{
    public function testConfiguredResolverReturnsStaticValue(): void
    {
        self::assertFalse((new ConfiguredAllowPrivateResolver())->allowPrivate());
        self::assertTrue((new ConfiguredAllowPrivateResolver(true))->allowPrivate());
    }

    public function testStaticDefaultBlocksPrivateTargets(): void
    {
        $guard = new RuntimeOutboundUrlGuard(new OutboundUrlGuardFactory(new ConfiguredAllowPrivateResolver(), resolveDns: false));

        self::assertFalse($guard->inspect('http://127.0.0.1/hook')->isValid());
        self::assertTrue($guard->inspect('https://1.1.1.1/hook')->isValid());
    }

    public function testResolverIsReadOnEveryCall(): void
    {
        $resolver = new ToggleResolver();
        $factory = new OutboundUrlGuardFactory($resolver, resolveDns: false);
        $guard = new RuntimeOutboundUrlGuard($factory);

        self::assertFalse($guard->inspect('http://127.0.0.1/hook')->isValid());
        self::assertFalse($factory->create()->inspect('http://127.0.0.1/hook')->isValid());

        $resolver->value = true;
        self::assertTrue($guard->inspect('http://127.0.0.1/hook')->isValid());
        self::assertTrue($guard->assertSafe('http://localhost/hook')->isValid());
        self::assertSame([], $guard->httpClientOptions('http://192.168.1.10/hook'));
        self::assertTrue($factory->create()->inspect('http://127.0.0.1/hook')->isValid());

        $resolver->value = false;
        try {
            $guard->assertSafe('http://127.0.0.1/hook');
            self::fail('Expected UnsafeOutboundUrlException');
        } catch (UnsafeOutboundUrlException) {
        }

        self::assertSame(7, $resolver->calls);
    }

    public function testMetadataStaysBlockedWhateverTheResolverReturns(): void
    {
        $resolver = new ToggleResolver();
        $resolver->value = true;
        $guard = new RuntimeOutboundUrlGuard(new OutboundUrlGuardFactory($resolver, resolveDns: false));

        foreach ([
            'http://169.254.169.254/latest/meta-data',
            'http://100.100.100.200/latest/meta-data',
            'http://2852039166/latest/meta-data',
            'http://[fe80::1]/latest/meta-data',
            'http://metadata.google.internal/computeMetadata/v1',
        ] as $url) {
            self::assertFalse($guard->inspect($url)->isValid(), $url);
        }

        $this->expectException(UnsafeOutboundUrlException::class);
        $guard->httpClientOptions('http://169.254.169.254/latest/meta-data');
    }

    public function testUsesInjectedDnsAndPinsWithResolveDnsOverride(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->method('dnsGetRecord')->willReturnCallback(
            static fn (string $host, int $type): array => match ($host) {
                'public.test' => DNS_A === $type ? [['ip' => '1.1.1.1']] : [],
                'meta.test' => DNS_A === $type ? [['ip' => '169.254.169.254']] : [],
                default => [],
            },
        );
        $dns->method('hostByNameL')->willReturn(false);

        $resolver = new ToggleResolver();
        $guard = new RuntimeOutboundUrlGuard(new OutboundUrlGuardFactory($resolver, resolveDns: true, dns: $dns));

        self::assertSame(['resolve' => ['public.test' => '1.1.1.1']], $guard->httpClientOptions('https://public.test/hook'));
        self::assertTrue($guard->inspect('http://mercure/hook', resolveDns: false)->isValid());
        self::assertTrue($guard->assertSafe('http://mercure/hook', resolveDns: false)->isValid());
        self::assertSame([], $guard->httpClientOptions('http://mercure/hook', resolveDns: false));

        $resolver->value = true;
        self::assertFalse($guard->inspect('https://meta.test/hook')->isValid());
    }
}

/**
 * Mutable resolver used to prove the switch is read per call.
 */
final class ToggleResolver implements AllowPrivateResolverInterface
{
    public bool $value = false;

    public int $calls = 0;

    public function allowPrivate(): bool
    {
        ++$this->calls;

        return $this->value;
    }
}
