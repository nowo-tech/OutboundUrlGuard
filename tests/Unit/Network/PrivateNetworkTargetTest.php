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
        self::assertFalse(PrivateNetworkTarget::isBlockedIp('100.100.100.200'));
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
}
