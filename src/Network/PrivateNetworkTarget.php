<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Network;

use function count;
use function is_string;
use function ord;
use function strlen;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

/**
 * Private, reserved, and cloud-metadata checks shared by every outbound URL policy.
 *
 * 100.64.0.0/10 is not a PHP private range, so Alibaba metadata 100.100.100.200 is
 * matched explicitly. Every IPv4 spelling curl accepts (inet_aton style: decimal,
 * octal, or hex parts, shorthand such as 127.1, 32-bit integers such as
 * 2852039166 or 0xa9fea9fe) and IPv4-mapped IPv6 are reduced to a canonical
 * dotted address before the check.
 */
final class PrivateNetworkTarget
{
    /**
     * True for localhost, metadata names, and reserved suffixes.
     *
     * @param string $host hostname without scheme
     */
    public static function isBlockedHostName(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        return 'localhost' === $host
            || 'metadata' === $host
            || 'metadata.google.internal' === $host
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal');
    }

    /**
     * True for loopback, RFC1918, link-local, unique-local, multicast, unspecified, and invalid IPs.
     *
     * @param string $ip address or integer host
     */
    public static function isBlockedIp(string $ip): bool
    {
        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return false === filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * Cloud instance-metadata endpoints. Always unsafe, even when private URLs are opted in.
     *
     * @param string $ip address, decimal host, or hex host
     */
    public static function isCloudMetadataIp(string $ip): bool
    {
        $canonical = self::canonicalIp($ip);
        if (null === $canonical) {
            return false;
        }

        if (false !== filter_var($canonical, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $packed = @inet_pton($canonical);

            // 169.254.0.0/16 link-local (includes 169.254.169.254).
            if (false !== $packed && str_starts_with($packed, "\xA9\xFE")) {
                return true;
            }

            // Alibaba Cloud metadata. Not covered by FILTER_FLAG_NO_PRIV_RANGE.
            return false !== $packed && "\x64\x64\x64\xC8" === $packed;
        }

        $packed = @inet_pton($canonical);

        // fe80::/10 link-local.
        return false !== $packed && 0xFE === ord($packed[0]) && 0x80 === (ord($packed[1]) & 0xC0);
    }

    /**
     * True for well-known cloud metadata hostnames.
     *
     * @param string $host hostname without scheme
     */
    public static function isCloudMetadataHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        return 'metadata' === $host || 'metadata.google.internal' === $host;
    }

    /**
     * Dotted IPv4, IPv6, the IPv4 embedded in an IPv4-mapped address, or an
     * inet_aton-style IPv4 (127.1, 0177.0.0.1, 0x7f.0.0.1, 2130706433). Null when
     * the value is a hostname.
     *
     * @param string $value host or address literal
     */
    public static function canonicalIp(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $value = substr($value, 1, -1);
        }

        if (false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $value;
        }

        if (false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($value);
            if (false !== $packed && 16 === strlen($packed) && "\0\0\0\0\0\0\0\0\0\0\xff\xff" === substr($packed, 0, 12)) {
                $embedded = @inet_ntop(substr($packed, 12));

                return is_string($embedded) ? $embedded : null;
            }

            return $value;
        }

        return self::ipv4FromInetAton($value);
    }

    /**
     * inet_aton() / WHATWG IPv4 forms that curl and browsers accept: one to four
     * dot-separated parts, each decimal, octal (leading 0), or hex (0x). The last
     * part fills the remaining bytes (127.1, 0177.0.0.1, 0x7f.0.0.1, 2130706433).
     */
    private static function ipv4FromInetAton(string $value): ?string
    {
        $parts = explode('.', $value);
        $count = count($parts);
        if ($count > 4) {
            return null;
        }

        $numbers = [];
        foreach ($parts as $part) {
            $number = self::inetAtonPart($part);
            if (null === $number) {
                return null;
            }
            $numbers[] = $number;
        }

        $address = (int) array_pop($numbers);
        if ($address > (0xFFFFFFFF >> (8 * ($count - 1)))) {
            return null;
        }

        $shift = 24;
        foreach ($numbers as $number) {
            if ($number > 0xFF) {
                return null;
            }
            $address |= $number << $shift;
            $shift -= 8;
        }

        $ip = long2ip($address);

        return is_string($ip) ? $ip : null;
    }

    /**
     * One inet_aton() part as an integer, or null when it is not numeric or exceeds 32 bits.
     */
    private static function inetAtonPart(string $part): ?int
    {
        if (str_starts_with($part, '0x')) {
            $digits = substr($part, 2);
            if ('' === $digits || !ctype_xdigit($digits)) {
                return null;
            }
            $base = 16;
        } elseif (ctype_digit($part)) {
            $digits = $part;
            $base = 10;
            if (strlen($part) > 1 && '0' === $part[0]) {
                $digits = substr($part, 1);
                $base = 8;
                if (1 === preg_match('/[89]/', $digits)) {
                    return null;
                }
            }
        } else {
            return null;
        }

        $digits = ltrim($digits, '0');
        if ('' === $digits) {
            return 0;
        }

        // 0xFFFFFFFF is 8 hex, 10 decimal, or 11 octal digits.
        if (strlen($digits) > [16 => 8, 10 => 10, 8 => 11][$base]) {
            return null;
        }

        $number = (int) base_convert($digits, $base, 10);

        return $number > 0xFFFFFFFF ? null : $number;
    }
}
