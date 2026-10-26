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

    public function testAllowPrivateWithoutDnsSkipsLookups(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->expects(self::never())->method('dnsGetRecord');

        $guard = new OutboundUrlGuard(allowPrivate: true, resolveDns: false, dns: $dns);

        self::assertTrue($guard->inspect('http://mercure/hook')->isValid());
        self::assertSame([], $guard->inspect('http://mercure/hook')->httpClientOptions());
    }

    public function testAllowPrivateStillResolvesAndBlocksMetadataAnswers(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->method('dnsGetRecord')->willReturnCallback(
            static fn (string $host, int $type): array => match ($host) {
                'mercure' => DNS_A === $type ? [['ip' => '172.18.0.5']] : [],
                'evil.test' => DNS_A === $type ? [['ip' => '169.254.169.254']] : [],
                'mixed.test' => DNS_A === $type ? [['ip' => '10.0.0.2'], ['ip' => '100.100.100.200']] : [],
                'v6meta.test' => DNS_AAAA === $type ? [['ipv6' => 'fe80::1']] : [],
                default => [],
            },
        );
        $dns->method('hostByNameL')->willReturn(false);

        $guard = new OutboundUrlGuard(allowPrivate: true, dns: $dns);

        self::assertSame('172.18.0.5', $this->pinnedIp($guard->httpClientOptions('http://mercure/hook'), 'mercure'));
        self::assertSame([], $guard->httpClientOptions('http://127.0.0.1/hook'));

        foreach (['http://evil.test/latest/meta-data', 'http://mixed.test/', 'http://v6meta.test/'] as $url) {
            $decision = $guard->inspect($url);
            self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $decision->result, $url);
            self::assertSame('Outbound URL must not target cloud metadata.', $decision->message, $url);
        }

        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('http://missing.test/')->result);
    }

    public function testBlocksInetAtonSpellingsOfPrivateAndMetadataAddresses(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->expects(self::never())->method('dnsGetRecord');

        $strict = new OutboundUrlGuard(resolveDns: false, dns: $dns);
        foreach ([
            'http://127.1/hook',
            'http://0177.0.0.1/hook',
            'http://0x7f.0.0.1/hook',
            'http://2130706433/hook',
            'http://0x7f000001/hook',
            'http://10.1/hook',
        ] as $url) {
            self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $strict->inspect($url)->result, $url);
        }

        $permissive = new OutboundUrlGuard(allowPrivate: true, resolveDns: false, dns: $dns);
        foreach ([
            'http://169.254.43518/latest/meta-data',
            'http://0251.0376.0251.0376/latest/meta-data',
            'http://0xa9.0xfe.0xa9.0xfe/latest/meta-data',
            'http://169.16689662/latest/meta-data',
        ] as $url) {
            self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $permissive->inspect($url)->result, $url);
        }
        self::assertTrue($permissive->inspect('http://127.1/hook')->isValid());
    }

    /**
     * Sec-004 M1: HttpClient keeps the trailing dot in the request host and matches
     * `resolve` keys exactly, so the pin must cover `example.com.` as well.
     */
    public function testPinCoversTheTrailingDotSpellingOfTheHost(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->method('dnsGetRecord')->willReturnCallback(
            static fn (string $host, int $type): array => 'example.com' === $host && DNS_A === $type ? [['ip' => '93.184.215.14']] : [],
        );
        $dns->method('hostByNameL')->willReturn(false);

        $guard = new OutboundUrlGuard(dns: $dns);

        $decision = $guard->inspect('http://Example.COM./hook');
        self::assertTrue($decision->isValid());
        self::assertSame('example.com', $decision->pinHost);
        self::assertSame(
            ['resolve' => ['example.com' => '93.184.215.14', 'example.com.' => '93.184.215.14']],
            $guard->httpClientOptions('http://example.com./hook'),
        );
        self::assertSame(
            ['resolve' => ['example.com' => '93.184.215.14', 'example.com.' => '93.184.215.14']],
            $guard->httpClientOptions('http://example.com/hook'),
        );

        // Two trailing dots would leave a host spelling the pin does not cover.
        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect('http://example.com../hook')->result);
    }

    /**
     * Sec-004 L1: CGNAT, multicast, and the other RFC 6890 non-global ranges are private.
     */
    public function testBlocksCgnatMulticastAndOtherNonGlobalRanges(): void
    {
        $guard = new OutboundUrlGuard(resolveDns: false);

        foreach ([
            'http://100.64.0.1/hook',
            'http://100.127.255.254/hook',
            'http://224.0.0.1/hook',
            'http://239.255.255.250/hook',
            'http://198.18.0.1/hook',
            'http://192.0.0.8/hook',
            'http://192.0.2.1/hook',
            'http://240.0.0.1/hook',
            'http://[ff02::1]/hook',
            'http://[fec0::1]/hook',
            'http://[64:ff9b:1::1]/hook',
            'http://[64:ff9b::7f00:1]/hook',
            'http://[2002:7f00:1::1]/hook',
            'http://[2001:db8::1]/hook',
            'http://[100::1]/hook',
        ] as $url) {
            self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $guard->inspect($url)->result, $url);
        }

        foreach ([
            'http://8.8.8.8/hook',
            'http://1.1.1.1/hook',
            'http://100.63.255.255/hook',
            'http://100.128.0.1/hook',
            'http://[2606:4700:4700::1111]/hook',
            'http://[64:ff9b::808:808]/hook',
        ] as $url) {
            self::assertTrue($guard->inspect($url)->isValid(), $url);
        }
    }

    /**
     * Sec-004 L1: DNS answers in non-global ranges, including IPv4-mapped or NAT64
     * spellings of loopback, are rejected before they can be pinned.
     */
    public function testRejectsNonGlobalDnsAnswers(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->method('dnsGetRecord')->willReturnCallback(
            static fn (string $host, int $type): array => match ($host) {
                'cgnat.test' => DNS_A === $type ? [['ip' => '100.64.0.1']] : [],
                'mapped.test' => DNS_AAAA === $type ? [['ipv6' => '::ffff:127.0.0.1']] : [],
                'nat64.test' => DNS_AAAA === $type ? [['ipv6' => '64:ff9b::a00:1']] : [],
                'multicast.test' => DNS_A === $type ? [['ip' => '224.0.0.1']] : [],
                default => [],
            },
        );
        $dns->method('hostByNameL')->willReturn(false);

        $guard = new OutboundUrlGuard(dns: $dns);

        foreach (['cgnat.test', 'mapped.test', 'nat64.test', 'multicast.test'] as $host) {
            $decision = $guard->inspect('http://'.$host.'/hook');
            self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $decision->result, $host);
            self::assertSame('Outbound URL resolves to a private address.', $decision->message, $host);
        }
    }

    /**
     * Sec-004 L2: hosts that are not hostname syntax never reach DNS. A leading `-`
     * would otherwise be parsed as a PHP CLI option by the DNS child process.
     */
    public function testRejectsInvalidHostnameSyntaxBeforeAnyLookup(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->expects(self::never())->method('dnsGetRecord');
        $dns->expects(self::never())->method('hostByNameL');

        foreach ([true, false] as $allowPrivate) {
            $guard = new OutboundUrlGuard(allowPrivate: $allowPrivate, dns: $dns);

            foreach ([
                'http://-h/hook',
                'http://-dauto_prepend_file=x/hook',
                'http://-a.example.com/hook',
                'http://a-.example.com/hook',
                'http://a..example.com/hook',
                'http://'.str_repeat('a', 64).'.example.com/hook',
                'http://'.implode('.', array_fill(0, 64, 'abc')).'/hook',
                'http://[not-ipv6]/hook',
                'http://exa$mple.com/hook',
            ] as $url) {
                $decision = $guard->inspect($url);
                self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $decision->result, $url);
                self::assertSame('Outbound URL host is not allowed.', $decision->message, $url);
            }
        }

        $offline = new OutboundUrlGuard(resolveDns: false, dns: $dns);
        self::assertTrue($offline->inspect('http://my_service/hook')->isValid());
        self::assertTrue($offline->inspect('http://a-b.'.str_repeat('c', 63).'/hook')->isValid());
    }

    /**
     * Sec-004 L3: AWS IPv6 metadata and NAT64 spellings of metadata stay blocked in every mode.
     */
    public function testBlocksAwsIpv6AndNat64MetadataInEveryMode(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->method('dnsGetRecord')->willReturnCallback(
            static fn (string $host, int $type): array => 'imds6.test' === $host && DNS_AAAA === $type ? [['ipv6' => 'fd00:ec2::254']] : [],
        );
        $dns->method('hostByNameL')->willReturn(false);

        foreach ([true, false] as $allowPrivate) {
            foreach ([true, false] as $resolveDns) {
                $guard = new OutboundUrlGuard(allowPrivate: $allowPrivate, resolveDns: $resolveDns, dns: $dns);
                foreach (['http://[fd00:ec2::254]/latest/meta-data', 'http://[64:ff9b::a9fe:a9fe]/latest/meta-data'] as $url) {
                    $decision = $guard->inspect($url);
                    self::assertSame('Outbound URL must not target cloud metadata.', $decision->message, $url);
                }
            }
        }

        $decision = (new OutboundUrlGuard(allowPrivate: true, dns: $dns))->inspect('http://imds6.test/latest/meta-data');
        self::assertSame('Outbound URL must not target cloud metadata.', $decision->message);
    }

    /**
     * Sec-004 L4: disguised literals (fullwidth digits, percent-encoding, IPv6 zone IDs)
     * are classified as the address HttpClient would use, or rejected.
     */
    public function testRejectsDisguisedLiteralsWithoutDns(): void
    {
        $dns = $this->createMock(HostnameDnsLookup::class);
        $dns->expects(self::never())->method('dnsGetRecord');

        $strict = new OutboundUrlGuard(resolveDns: false, dns: $dns);
        foreach ([
            "http://\u{FF11}\u{FF12}\u{FF17}.\u{FF10}.\u{FF10}.\u{FF11}/hook",
            "http://127\u{3002}0\u{3002}0\u{3002}1/hook",
            'http://[fe80::1%25eth0]/hook',
            'http://[::1%25lo]/hook',
            'http://%31%32%37.0.0.1/hook',
        ] as $url) {
            self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $strict->inspect($url)->result, $url);
        }

        $permissive = new OutboundUrlGuard(allowPrivate: true, resolveDns: false, dns: $dns);
        self::assertSame(
            'Outbound URL must not target cloud metadata.',
            $permissive->inspect("http://\u{FF11}\u{FF16}\u{FF19}.\u{FF12}\u{FF15}\u{FF14}.\u{FF11}\u{FF16}\u{FF19}.\u{FF12}\u{FF15}\u{FF14}/")->message,
        );
        self::assertSame(OutboundUrlDecision::RESULT_UNSAFE, $permissive->inspect('http://[fe80::1%25eth0]/hook')->result);

        // Ordinary IDN hosts still pass (as their punycode form).
        self::assertTrue($strict->inspect("https://b\u{FC}cher.example/hook")->isValid());
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
