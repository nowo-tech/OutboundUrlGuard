<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Exception;

use InvalidArgumentException;

/**
 * The URL is missing, not http(s), or targets a blocked network.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class UnsafeOutboundUrlException extends InvalidArgumentException
{
}
