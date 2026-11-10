<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Guard;

use Nowo\OutboundUrlGuardBundle\Exception\UnsafeOutboundUrlException;

/**
 * The outbound URL policy as a port (1.5, DDD P2).
 *
 * Type-hint this interface in the adapter that opens the socket. The container alias points at
 * {@see RuntimeOutboundUrlGuard}, which reads `allow_private` through the configured
 * {@see AllowPrivateResolverInterface} (the static `allow_private` value when no resolver is set), so it answers
 * exactly like {@see OutboundUrlGuard} with the bundle configuration.
 *
 * {@see OutboundUrlGuard} does not implement it: its second argument is `$allowPrivate`, not `$resolveDns`. Outside
 * the container, build `new RuntimeOutboundUrlGuard(new OutboundUrlGuardFactory(new ConfiguredAllowPrivateResolver(false)))`
 * or your own test double.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface OutboundUrlGuardInterface
{
    /**
     * Classifies a URL without throwing.
     *
     * @param string|null $url        candidate http(s) URL
     * @param bool|null   $resolveDns overrides `resolve_dns` when not null
     */
    public function inspect(?string $url, ?bool $resolveDns = null): OutboundUrlDecision;

    /**
     * Rejects an invalid or unsafe URL.
     *
     * @param string    $url        candidate http(s) URL
     * @param bool|null $resolveDns overrides `resolve_dns` when not null
     *
     * @throws UnsafeOutboundUrlException when the URL is invalid or unsafe
     */
    public function assertSafe(string $url, ?bool $resolveDns = null): OutboundUrlDecision;

    /**
     * Asserts the URL and returns HttpClient resolve options when a pin exists.
     *
     * @param string    $url        candidate http(s) URL
     * @param bool|null $resolveDns overrides `resolve_dns` when not null
     *
     * @return array{resolve?: array<string, string>}
     *
     * @throws UnsafeOutboundUrlException when the URL is invalid or unsafe
     */
    public function httpClientOptions(string $url, ?bool $resolveDns = null): array;
}
