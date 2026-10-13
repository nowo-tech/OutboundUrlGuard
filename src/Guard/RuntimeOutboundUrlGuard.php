<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Guard;

use Nowo\OutboundUrlGuardBundle\Exception\UnsafeOutboundUrlException;

/**
 * Same API as {@see OutboundUrlGuard}, but `allowPrivate` comes from the configured
 * {@see AllowPrivateResolverInterface} on every call instead of a compiled container parameter.
 *
 * Inject it where the private-URL switch is an operator setting that can change at runtime.
 * Cloud metadata stays blocked whatever the resolver returns.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class RuntimeOutboundUrlGuard
{
    /**
     * @param OutboundUrlGuardFactory $factory builds a guard from the resolver's current answer
     */
    public function __construct(
        private OutboundUrlGuardFactory $factory,
    ) {
    }

    /**
     * Classifies a URL without throwing.
     *
     * @param string|null $url        candidate http(s) URL
     * @param bool|null   $resolveDns overrides `resolve_dns` when not null
     */
    public function inspect(?string $url, ?bool $resolveDns = null): OutboundUrlDecision
    {
        return $this->factory->create()->inspect($url, resolveDns: $resolveDns);
    }

    /**
     * Rejects an invalid or unsafe URL.
     *
     * @param string    $url        candidate http(s) URL
     * @param bool|null $resolveDns overrides `resolve_dns` when not null
     *
     * @throws UnsafeOutboundUrlException when the URL is invalid or unsafe
     */
    public function assertSafe(string $url, ?bool $resolveDns = null): OutboundUrlDecision
    {
        return $this->factory->create()->assertSafe($url, resolveDns: $resolveDns);
    }

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
    public function httpClientOptions(string $url, ?bool $resolveDns = null): array
    {
        return $this->factory->create()->httpClientOptions($url, resolveDns: $resolveDns);
    }
}
