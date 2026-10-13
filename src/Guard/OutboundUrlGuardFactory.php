<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Guard;

use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;

/**
 * Builds an {@see OutboundUrlGuard} whose `allowPrivate` default is read from the resolver at creation time.
 *
 * Call {@see create()} per check (it is cheap) instead of keeping the returned guard: the resolver is
 * read on every call, so a runtime toggle applies to the next check. Cloud metadata stays blocked
 * whatever the resolver returns.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class OutboundUrlGuardFactory
{
    /**
     * @param AllowPrivateResolverInterface $allowPrivateResolver read on every {@see create()} call
     * @param bool                          $resolveDns           value of `resolve_dns`
     * @param HostnameDnsLookup             $dns                  shared DNS lookup service (`dns_timeout`, `php_binary`)
     */
    public function __construct(
        private AllowPrivateResolverInterface $allowPrivateResolver,
        private bool $resolveDns = true,
        private HostnameDnsLookup $dns = new HostnameDnsLookup(),
    ) {
    }

    /**
     * Returns a guard configured with the resolver's current answer.
     */
    public function create(): OutboundUrlGuard
    {
        return new OutboundUrlGuard(
            allowPrivate: $this->allowPrivateResolver->allowPrivate(),
            resolveDns: $this->resolveDns,
            dns: $this->dns,
        );
    }
}
