<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Guard;

/**
 * Default resolver: returns the static `allow_private` config value.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ConfiguredAllowPrivateResolver implements AllowPrivateResolverInterface
{
    /**
     * @param bool $allowPrivate value of `nowo_outbound_url_guard.allow_private`
     */
    public function __construct(
        private bool $allowPrivate = false,
    ) {
    }

    /**
     * Returns the configured value.
     */
    public function allowPrivate(): bool
    {
        return $this->allowPrivate;
    }
}
