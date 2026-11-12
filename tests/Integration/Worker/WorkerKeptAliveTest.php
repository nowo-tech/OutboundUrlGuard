<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Integration\Worker;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\Compiler\DnsLookupClassPass;
use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookupInterface;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;
use Nowo\OutboundUrlGuardBundle\Guard\RuntimeOutboundUrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function gc_collect_cycles;
use function memory_get_usage;
use function str_starts_with;

use const DNS_A;

/**
 * REQ-RUNTIME-002: ONE kept-alive container, the services resetter never called. The shared guards serve 1,200 calls
 * alternating two tenants whose hosts resolve differently (public address pinned vs private address refused). Each
 * decision carries only its own call's data (no pin or verdict of an earlier call) and memory stays flat.
 */
final class WorkerKeptAliveTest extends TestCase
{
    private const CALLS = 1200;
    private const MEMORY_MARGIN = 4 * 1024 * 1024;

    public function testAlternatingResolverOutcomesNeverLeakAcrossCallsAndMemoryStaysFlat(): void
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load([['dns_lookup' => 'app.tenant_dns']], $container);
        $container->setDefinition('app.tenant_dns', new Definition(TenantDnsLookup::class));
        $container->addCompilerPass(new DnsLookupClassPass());
        $container->compile();
        $runtime = $container->get(RuntimeOutboundUrlGuard::class);
        $static = $container->get(OutboundUrlGuard::class);
        self::assertInstanceOf(RuntimeOutboundUrlGuard::class, $runtime);
        self::assertInstanceOf(OutboundUrlGuard::class, $static);

        $baseline = 0;
        for ($i = 0; $i < self::CALLS; ++$i) {
            $public = 0 === $i % 2;
            $host = ($public ? 'alice-' : 'bob-').$i.'.example';
            $guard = $i % 4 < 2 ? $runtime : $static;

            $decision = $guard->inspect('https://'.$host.'/hook');
            if ($public) {
                self::assertTrue($decision->isValid(), 'call '.$i);
                self::assertSame('93.184.216.'.(1 + $i % 250), $decision->pinIp, 'call '.$i.' pins its own address');
                self::assertSame($host, $decision->pinHost);
            } else {
                self::assertFalse($decision->isValid(), 'call '.$i.' keeps no verdict of the previous public call');
                self::assertNull($decision->pinIp);
                self::assertNull($decision->pinHost);
            }

            if (100 === $i) {
                $baseline = $this->collectedMemory();
            }
        }

        self::assertLessThan(self::MEMORY_MARGIN, $this->collectedMemory() - $baseline, 'memory stays flat over many calls');
    }

    private function collectedMemory(): int
    {
        gc_collect_cycles();

        return memory_get_usage();
    }
}

/**
 * Hosts of the tenant "alice-N" resolve to a public address derived from N; hosts of "bob-N" to a private one.
 */
final class TenantDnsLookup implements HostnameDnsLookupInterface
{
    public function dnsGetRecord(string $hostname, int $type): array
    {
        if (DNS_A !== $type) {
            return [];
        }
        $n = (int) substr($hostname, (int) strrpos($hostname, '-') + 1);
        $ip = str_starts_with($hostname, 'alice-') ? '93.184.216.'.(1 + $n % 250) : '10.0.0.'.(1 + $n % 250);

        return [['host' => $hostname, 'type' => 'A', 'ip' => $ip]];
    }

    public function hostByNameL(string $hostname): array|false
    {
        return false;
    }
}
