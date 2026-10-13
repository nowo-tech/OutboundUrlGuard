<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Guard;

/**
 * Supplies the "allow private URLs" switch at call time (for example an operator setting stored in a database).
 *
 * {@see OutboundUrlGuardFactory} and {@see RuntimeOutboundUrlGuard} read it on every call, so implementations
 * must be cheap and must not cache the answer across requests. Cloud metadata stays blocked whatever it returns.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface AllowPrivateResolverInterface
{
    /**
     * Returns true when loopback, RFC1918, and names such as localhost are allowed for the next check.
     */
    public function allowPrivate(): bool;
}
