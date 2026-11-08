<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Integration\DependencyInjection;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\Compiler\DnsLookupClassPass;
use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookupInterface;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuardFactory;
use Nowo\OutboundUrlGuardBundle\Guard\RuntimeOutboundUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

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

    /**
     * @return iterable<string, array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: list<mixed>|false}>
     */
    public static function nonIpAnswers(): iterable
    {
        yield 'host name as A record' => [[['ip' => 'evil.example']], [], false];
        yield 'metadata name in the fallback' => [[], [], ['metadata.google.internal']];
        yield 'inet_aton form' => [[['ip' => '127.1']], [], false];
        yield 'bracketed IPv6' => [[], [['ipv6' => '[::1]']], false];
        yield 'non-string fallback' => [[], [], [2130706433]];
    }

    /**
     * IL-L1: only real IP addresses are classified and pinned, in both modes.
     *
     * @param array<int, array<string, mixed>> $a
     * @param array<int, array<string, mixed>> $aaaa
     * @param list<mixed>|false                $fallback
     */
    #[DataProvider('nonIpAnswers')]
    public function testNonIpAnswersAreNeverPinned(array $a, array $aaaa, array|false $fallback): void
    {
        foreach ([false, true] as $allowPrivate) {
            $guard = $this->scripted(new Definition(ScriptedDnsLookup::class, [$a, $aaaa, $fallback, false]), $allowPrivate);
            $decision = $guard->inspect('https://x.example/hook');
            self::assertFalse($decision->isValid(), 'allow_private: '.($allowPrivate ? 'true' : 'false'));
            self::assertSame('Outbound URL host could not be resolved.', $decision->message);
            self::assertNull($decision->pinIp);
        }
    }

    public function testNonIpEntriesAreDroppedAndTheCanonicalIpIsPinned(): void
    {
        foreach ([false, true] as $allowPrivate) {
            $guard = $this->scripted(new Definition(ScriptedDnsLookup::class, [[['ip' => 'evil.example']], [['ipv6' => '::ffff:93.184.216.34']], false, false]), $allowPrivate);
            $decision = $guard->inspect('https://x.example/hook');
            self::assertTrue($decision->isValid());
            self::assertSame('93.184.216.34', $decision->pinIp);
        }

        $private = $this->scripted(new Definition(ScriptedDnsLookup::class, [[], [['ipv6' => '::ffff:169.254.169.254']], false, false]), true);
        self::assertFalse($private->inspect('https://x.example/hook')->isValid(), 'metadata behind a mapped address stays blocked');
    }

    /**
     * IL-I1: a throwing lookup fails closed with a result.
     */
    public function testAThrowingLookupFailsClosedWithoutThrowing(): void
    {
        foreach ([false, true] as $allowPrivate) {
            $guard = $this->scripted(new Definition(ScriptedDnsLookup::class, [[], [], false, true]), $allowPrivate);
            $decision = $guard->inspect('https://x.example/hook');
            self::assertFalse($decision->isValid());
            self::assertSame('Outbound URL host could not be resolved.', $decision->message);
        }
    }

    /**
     * IL-I2: a `dns_lookup` service of the wrong class fails the compile.
     */
    public function testALookupOfTheWrongClassFailsTheCompile(): void
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load([['dns_lookup' => 'app.not_a_lookup']], $container);
        $container->setDefinition('app.not_a_lookup', new Definition(stdClass::class));
        $container->addCompilerPass(new DnsLookupClassPass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');
        $container->compile();
    }

    public function testTheClassCheckSkipsUnresolvableClassesAndMissingAliases(): void
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load([['dns_lookup' => 'app.factory_lookup']], $container);
        $container->setDefinition('app.factory_lookup', (new Definition())->setFactory([self::class, 'makeLookup']));
        (new DnsLookupClassPass())->process($container);

        (new DnsLookupClassPass())->process(new ContainerBuilder());
        $this->addToAssertionCount(1);
    }

    public static function makeLookup(): HostnameDnsLookupInterface
    {
        return new FixedDnsLookup();
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

    private function scripted(Definition $lookup, bool $allowPrivate): OutboundUrlGuard
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load([['dns_lookup' => 'app.scripted', 'allow_private' => $allowPrivate]], $container);
        $container->setDefinition('app.scripted', $lookup);
        $container->addCompilerPass(new DnsLookupClassPass());
        $container->compile();
        $guard = $container->get(OutboundUrlGuard::class);
        self::assertInstanceOf(OutboundUrlGuard::class, $guard);

        return $guard;
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    private function compile(array $configs, bool $public = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load($configs, $container);
        $container->setDefinition('app.test_dns', new Definition(FixedDnsLookup::class));
        $container->addCompilerPass(new DnsLookupClassPass());
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

/**
 * Lookup with scripted answers, or one that throws.
 */
final class ScriptedDnsLookup implements HostnameDnsLookupInterface
{
    /**
     * @param array<int, array<string, mixed>> $a
     * @param array<int, array<string, mixed>> $aaaa
     * @param list<mixed>|false                $fallback
     */
    public function __construct(private readonly array $a, private readonly array $aaaa, private readonly array|false $fallback, private readonly bool $throw)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dnsGetRecord(string $hostname, int $type): array
    {
        if ($this->throw) {
            throw new RuntimeException('resolver down');
        }

        return DNS_A === $type ? $this->a : $this->aaaa;
    }

    /**
     * @return list<mixed>|false
     */
    public function hostByNameL(string $hostname): array|false
    {
        return $this->fallback;
    }
}
