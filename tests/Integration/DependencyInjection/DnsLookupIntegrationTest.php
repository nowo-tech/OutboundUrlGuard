<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Integration\DependencyInjection;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookupInterface;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuardFactory;
use Nowo\OutboundUrlGuardBundle\Guard\RuntimeOutboundUrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use const DNS_A;

final class DnsLookupIntegrationTest extends TestCase
{
    public function testDefaultUsesTheBundledLookup(): void
    {
        $container = $this->compile([['dns_timeout' => 1.5, 'php_binary' => '']], public: true);

        $lookup = $container->get(HostnameDnsLookupInterface::class);
        self::assertInstanceOf(HostnameDnsLookup::class, $lookup);
        self::assertSame(1.5, $lookup->timeoutSeconds());
        self::assertNull($lookup->phpBinary());
    }

    public function testCustomLookupFeedsEveryGuard(): void
    {
        $container = $this->compile([['dns_lookup' => '@app.test_dns']]);

        $static = $container->get(OutboundUrlGuard::class);
        self::assertInstanceOf(OutboundUrlGuard::class, $static);
        $decision = $static->inspect('https://hooks.example.test/in');
        self::assertTrue($decision->isValid());
        self::assertSame('93.184.216.34', $decision->pinIp);

        $factory = $container->get(OutboundUrlGuardFactory::class);
        self::assertInstanceOf(OutboundUrlGuardFactory::class, $factory);
        self::assertTrue($factory->create()->inspect('https://hooks.example.test/in')->isValid());

        $runtime = $container->get(RuntimeOutboundUrlGuard::class);
        self::assertInstanceOf(RuntimeOutboundUrlGuard::class, $runtime);
        self::assertTrue($runtime->inspect('https://hooks.example.test/in')->isValid());
    }

    public function testCustomLookupCannotAllowPrivateOrMetadataAnswers(): void
    {
        $container = $this->compile([['dns_lookup' => 'app.test_dns']]);

        $static = $container->get(OutboundUrlGuard::class);
        self::assertInstanceOf(OutboundUrlGuard::class, $static);
        self::assertFalse($static->inspect('https://loopback.example.test/in')->isValid());
        self::assertFalse($static->inspect('https://metadata.example.test/in')->isValid());
        self::assertFalse($static->inspect('http://127.0.0.1/in')->isValid());

        $private = $this->compile([['dns_lookup' => 'app.test_dns', 'allow_private' => true]])->get(OutboundUrlGuard::class);
        self::assertInstanceOf(OutboundUrlGuard::class, $private);
        self::assertTrue($private->inspect('https://loopback.example.test/in')->isValid());
        self::assertFalse($private->inspect('https://metadata.example.test/in')->isValid());
    }

    public function testRejectsEmptyLookupId(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new NowoOutboundUrlGuardExtension())->load([['dns_lookup' => '@']], new ContainerBuilder());
    }

    public function testRejectsNonStringLookupId(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new NowoOutboundUrlGuardExtension())->load([['dns_lookup' => 42]], new ContainerBuilder());
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    private function compile(array $configs, bool $public = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load($configs, $container);
        $container->setDefinition('app.test_dns', new Definition(FixedDnsLookup::class));
        if ($public) {
            $container->getAlias(HostnameDnsLookupInterface::class)->setPublic(true);
        }
        $container->compile();

        return $container;
    }
}

/**
 * Test-style lookup with fixed answers (no network).
 */
final class FixedDnsLookup implements HostnameDnsLookupInterface
{
    private const ANSWERS = [
        'hooks.example.test' => '93.184.216.34',
        'loopback.example.test' => '127.0.0.1',
        'metadata.example.test' => '169.254.169.254',
    ];

    public function dnsGetRecord(string $hostname, int $type): array|false
    {
        if (DNS_A !== $type) {
            return [];
        }
        if (!isset(self::ANSWERS[$hostname])) {
            return false;
        }

        return [['host' => $hostname, 'type' => 'A', 'ip' => self::ANSWERS[$hostname]]];
    }

    public function hostByNameL(string $hostname): array|false
    {
        return false;
    }
}
