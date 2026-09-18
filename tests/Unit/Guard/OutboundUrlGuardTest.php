<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Unit\Guard;

use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use Nowo\OutboundUrlGuardBundle\Exception\UnsafeOutboundUrlException;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlDecision;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;
use PHPUnit\Framework\TestCase;

use const DNS_A;
use const DNS_AAAA;

final class OutboundUrlGuardTest extends TestCase
{
    public function testRejectsInvalidUrls(): void
    {
        $guard = new OutboundUrlGuard(resolveDns: false);

        self::assertSame(OutboundUrlDecision::RESULT_INVALID, $guard->inspect('')->result);
        self::assertSame(OutboundUrlDecision::RESULT_INVALID, $guard->inspect('mercure')->result);
        self::assertSame(OutboundUrlDecision::RESULT_INVALID, $guard->inspect('ftp://mercure/hook')->result);
        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('http://./hook')->result);
    }

    public function testBlocksPrivateTargetsUnlessOptedIn(): void
    {
        $guard = new OutboundUrlGuard(resolveDns: false);

        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('http://127.0.0.1/hook')->result);
        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('http://192.168.1.10/hook')->result);
        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('https://localhost/hook')->result);
        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('http://hub.local/hook')->result);
        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('https://[::1]/hook')->result);
        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('https://[fd12:3456:789a::1]/hook')->result);

        self::assertTrue($guard->inspect('http://127.0.0.1/hook', allowPrivate: true)->isValid());
        self::assertTrue($guard->inspect('https://localhost/hook', allowPrivate: true)->isValid());
        self::assertSame([], $guard->httpClientOptions('http://127.0.0.1/hook', allowPrivate: true));
    }

    public function testBlocksMetadataEvenWhenPrivateIsAllowed(): void
    {
        $guard = new OutboundUrlGuard(allowPrivate: true, resolveDns: false);

        foreach ([
            'http://169.254.169.254/latest/meta-data',
            'http://100.100.100.200/latest/meta-data',
            'http://2852039166/latest/meta-data',
            'http://[::ffff:169.254.169.254]/latest/meta-data',
            'http://[fe80::1]/latest/meta-data',
            'http://metadata.google.internal/computeMetadata/v1',
            'http://metadata/latest/meta-data',
        ] as $url) {
            self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect($url)->result, $url);
        }
    }

    public function testSkipsDnsForDockerNamesAndPinsPublicLiteralsWithoutResolve(): void
    {
        $guard = new OutboundUrlGuard(resolveDns: false);

        self::assertTrue($guard->inspect('http://mercure/.well-known/mercure')->isValid());
        self::assertTrue($guard->inspect('http://php/.well-known/mercure')->isValid());
        self::assertSame([], $guard->httpClientOptions('https://1.1.1.1/hook'));
        self::assertSame([], $guard->httpClientOptions('https://[2606:4700:4700::1111]/hook'));
    }

    public function testPinsPublicDnsAndRejectsPrivateOrMissingAnswers(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->method('dnsGetRecord')->willReturnCallback(
            static fn (string $host, int $type): array => match ($host) {
                'both.test' => DNS_A === $type
                    ? [['ip' => '1.1.1.1']]
                    : [['ipv6' => '2606:4700:4700::1111']],
                'v6.test' => DNS_AAAA === $type ? [['ipv6' => '2606:4700:4700::1111']] : [['ip' => '']],
                'meta.test' => DNS_A === $type ? [['ip' => '100.100.100.200']] : [],
                default => [],
            },
        );
        $dns->method('hostByNameL')->willReturnCallback(
            static fn (string $host): array|false => match ($host) {
                'fallback.test' => ['8.8.8.8'],
                'private.test' => ['127.0.0.1'],
                'missing.test' => false,
                default => false,
            },
        );

        $guard = new OutboundUrlGuard(dns: $dns);

        self::assertSame('1.1.1.1', $this->pinnedIp($guard->httpClientOptions('https://both.test/hook'), 'both.test'));
        self::assertSame('2606:4700:4700::1111', $this->pinnedIp($guard->httpClientOptions('https://v6.test/hook'), 'v6.test'));
        self::assertSame('8.8.8.8', $this->pinnedIp($guard->httpClientOptions('https://fallback.test/hook'), 'fallback.test'));

        foreach (['https://meta.test/hook', 'https://private.test/hook', 'https://missing.test/hook'] as $url) {
            try {
                $guard->assertSafe($url);
                self::fail('Expected rejection for '.$url);
            } catch (UnsafeOutboundUrlException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testAllowPrivateSkipsDns(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->expects(self::never())->method('dnsGetRecord');

        $guard = new OutboundUrlGuard(allowPrivate: true, dns: $dns);

        self::assertTrue($guard->inspect('http://mercure/hook')->isValid());
        self::assertSame([], $guard->inspect('http://mercure/hook')->httpClientOptions());
    }

    /**
     * @param array{resolve?: array<string, string>} $options
     */
    private function pinnedIp(array $options, string $host): string
    {
        if (!isset($options['resolve'][$host])) {
            self::fail('Missing resolve pin for '.$host);
        }

        return $options['resolve'][$host];
    }
}
