<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Blocks SSRF on outbound http(s) URLs (private networks, cloud metadata, optional DNS pin).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class NowoOutboundUrlGuardBundle extends Bundle
{
    /**
     * Registers the bundle extension explicitly so the alias stays stable.
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            // @igor-ignore - Symfony Bundle caches Extension once at boot; not request-scoped state
            $this->extension = new NowoOutboundUrlGuardExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
