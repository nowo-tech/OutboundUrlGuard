<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Guard;

/**
 * Closed set of outcomes for one outbound URL check.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
enum OutboundUrlResult: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Unsafe = 'unsafe';
}
