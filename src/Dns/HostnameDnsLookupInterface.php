<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Dns;

/**
 * Answers the DNS questions the guard asks for one hostname.
 *
 * The guard classifies every answer itself (private, reserved, cloud metadata) before it pins one, so an
 * implementation only supplies addresses: it cannot allow a private or metadata target, nor switch the guard off.
 * Configure a custom one with `dns_lookup` (tests that need fixed answers, for example).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface HostnameDnsLookupInterface
{
    /**
     * Resolve A or AAAA records for a hostname, shaped like dns_get_record() (`ip` for A, `ipv6` for AAAA).
     *
     * @param string $hostname host to resolve
     * @param int    $type     DNS_A or DNS_AAAA
     *
     * @return array<int, array<string, mixed>>|false
     */
    public function dnsGetRecord(string $hostname, int $type): array|false;

    /**
     * IPv4 fallback when dnsGetRecord() returns nothing, shaped like gethostbynamel().
     *
     * @param string $hostname host to resolve
     *
     * @return list<string>|false
     */
    public function hostByNameL(string $hostname): array|false;
}
