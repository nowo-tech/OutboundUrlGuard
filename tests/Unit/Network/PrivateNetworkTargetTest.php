<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Unit\Network;

use Nowo\OutboundUrlGuardBundle\Network\PrivateNetworkTarget;
use PHPUnit\Framework\TestCase;

final class PrivateNetworkTargetTest extends TestCase
{
    public function testBlocksPrivateAndReservedIps(): void
    {
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('127.0.0.1'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('10.0.0.1'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('192.168.1.1'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('169.254.169.254'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('::1'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('not-an-ip'));
        self::assertFalse(PrivateNetworkTarget::isBlockedIp('8.8.8.8'));
        self::assertFalse(PrivateNetworkTarget::isBlockedIp('1.1.1.1'));
        // CGNAT (100.64.0.0/10) is not globally reachable; Alibaba metadata sits inside it.
        self::assertTrue(PrivateNetworkTarget::isBlockedIp('100.100.100.200'));
    }

    public function testBlocksSensitiveHostnames(): void
    {
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('localhost'));
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('hub.local'));
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('svc.internal'));
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('metadata'));
        self::assertTrue(PrivateNetworkTarget::isBlockedHostName('metadata.google.internal'));
        self::assertFalse(PrivateNetworkTarget::isBlockedHostName('mercure'));
        self::assertFalse(PrivateNetworkTarget::isBlockedHostName('hub.example.com'));
    }

    public function testCloudMetadataDetection(): void
    {
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('169.254.169.254'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('100.100.100.200'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('::ffff:169.254.169.254'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('::ffff:100.100.100.200'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('2852039166'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('0xa9fea9fe'));
        self::assertSame('169.254.169.254', PrivateNetworkTarget::canonicalIp('2852039166'));
        self::assertSame('169.254.169.254', PrivateNetworkTarget::canonicalIp('[::ffff:169.254.169.254]'));
        self::assertSame('8.8.8.8', PrivateNetworkTarget::canonicalIp('0x08080808'));
        self::assertSame('2001:db8::1', PrivateNetworkTarget::canonicalIp('2001:db8::1'));
        self::assertNull(PrivateNetworkTarget::canonicalIp('mercure'));
        self::assertNull(PrivateNetworkTarget::canonicalIp('0x'));
        self::assertNull(PrivateNetworkTarget::canonicalIp('0xzzzzzzzz'));
        self::assertNull(PrivateNetworkTarget::canonicalIp('4294967296'));
        self::assertNull(PrivateNetworkTarget::canonicalIp('12345678901'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('fe80::1'));
        self::assertFalse(PrivateNetworkTarget::isCloudMetadataIp('8.8.8.8'));
        self::assertFalse(PrivateNetworkTarget::isCloudMetadataIp('not-an-ip'));
        self::assertFalse(PrivateNetworkTarget::isCloudMetadataIp('2001:db8::1'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataHost('metadata'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataHost('metadata.google.internal'));
        self::assertFalse(PrivateNetworkTarget::isCloudMetadataHost('mercure'));
    }

    public function testCanonicalIpNormalizesInetAtonForms(): void
    {
        foreach ([
            '127.1' => '127.0.0.1',
            '127.0.1' => '127.0.0.1',
            '0177.0.0.1' => '127.0.0.1',
            '0x7f.0.0.1' => '127.0.0.1',
            '0X7F.1' => '127.0.0.1',
            '2130706433' => '127.0.0.1',
            '017700000001' => '127.0.0.1',
            '0x7f000001' => '127.0.0.1',
            '169.254.43518' => '169.254.169.254',
            '0251.0376.0251.0376' => '169.254.169.254',
            '0xa9.0xfe.0xa9.0xfe' => '169.254.169.254',
            '00.0x0.0.0' => '0.0.0.0',
            '0x00000000a9fea9fe' => '169.254.169.254',
        ] as $input => $expected) {
            self::assertSame($expected, PrivateNetworkTarget::canonicalIp((string) $input), (string) $input);
        }

        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('169.254.43518'));
        self::assertTrue(PrivateNetworkTarget::isBlockedIp((string) PrivateNetworkTarget::canonicalIp('127.1')));

        foreach ([
            '1.2.3.4.5',
            '1..2',
            '256.1.1.1',
            '1.256.1.1',
            '1.2.65536',
            '08.0.0.1',
            '0x.1',
            '037777777777777',
            '1.2.3.example',
            'example.123',
        ] as $input) {
            self::assertNull(PrivateNetworkTarget::canonicalIp($input), $input);
        }
    }

    public function testBlocksNonGlobalRangesButNotPublicAddresses(): void
    {
        foreach ([
            '100.64.0.1',
            '100.127.255.254',
            '224.0.0.1',
            '239.255.255.250',
            '198.18.0.1',
            '203.0.113.1',
            '::ffff:127.0.0.1',
            '::ffff:100.64.0.1',
            '64:ff9b::7f00:1',
            '64:ff9b:1::1',
            '2002:a00:1::1',
            'ff02::1',
            'ff0e::1',
            'fec0::1',
            'fd00:ec2::254',
            '2001::1',
        ] as $ip) {
            self::assertTrue(PrivateNetworkTarget::isBlockedIp($ip), $ip);
        }

        foreach (['8.8.8.8', '1.1.1.1', '100.63.255.255', '100.128.0.1', '223.255.255.254', '2606:4700:4700::1111', '64:ff9b::808:808', '2002:808:808::1'] as $ip) {
            self::assertFalse(PrivateNetworkTarget::isBlockedIp($ip), $ip);
        }
    }

    public function testAwsIpv6MetadataAndEmbeddedIpv4(): void
    {
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('fd00:ec2::254'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('[fd00:0ec2:0000::0254]'));
        self::assertTrue(PrivateNetworkTarget::isCloudMetadataIp('64:ff9b::a9fe:a9fe'));
        self::assertFalse(PrivateNetworkTarget::isCloudMetadataIp('fd00:ec2::253'));
        self::assertSame('127.0.0.1', PrivateNetworkTarget::canonicalIp('64:ff9b::7f00:1'));
        self::assertSame('127.0.0.1', PrivateNetworkTarget::canonicalIp('2002:7f00:1::1'));
        self::assertSame('64:ff9b:1::1', PrivateNetworkTarget::canonicalIp('64:ff9b:1::1'));
    }

    public function testAsciiHostAndHostnameSyntax(): void
    {
        self::assertSame('example.com', PrivateNetworkTarget::asciiHost('Example.COM'));
        self::assertSame('127.0.0.1', PrivateNetworkTarget::asciiHost("\u{FF11}\u{FF12}\u{FF17}.\u{FF10}.\u{FF10}.\u{FF11}"));
        self::assertSame('xn--bcher-kva.example', PrivateNetworkTarget::asciiHost("B\u{DC}cher.example"));
        self::assertNull(PrivateNetworkTarget::asciiHost("b\u{FC}cher.example", false));
        self::assertNull(PrivateNetworkTarget::asciiHost("\u{FF11}27.0.0.1", false));
        self::assertNull(PrivateNetworkTarget::asciiHost("a\u{200D}b.example"));
        self::assertNull(PrivateNetworkTarget::asciiHost('[fe80::1%25eth0]'));
        self::assertNull(PrivateNetworkTarget::asciiHost('%31%32%37.0.0.1'));
        self::assertNull(PrivateNetworkTarget::asciiHost('exa mple.com'));
        self::assertNull(PrivateNetworkTarget::asciiHost("exa\tmple.com"));

        self::assertTrue(PrivateNetworkTarget::isValidHostName('example.com'));
        self::assertTrue(PrivateNetworkTarget::isValidHostName('my_service'));
        self::assertTrue(PrivateNetworkTarget::isValidHostName('a'));
        self::assertTrue(PrivateNetworkTarget::isValidHostName('xn--bcher-kva.example'));
        foreach (['', '-h', 'a-', 'a.-b', 'a..b', 'example.com.', '[::1]', 'a=b', str_repeat('a', 64), implode('.', array_fill(0, 64, 'abc'))] as $host) {
            self::assertFalse(PrivateNetworkTarget::isValidHostName($host), $host);
        }
    }
}
