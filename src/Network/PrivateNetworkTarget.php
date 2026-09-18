<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Network;

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
 * matched explicitly. Decimal and hex 32-bit hosts (2852039166, 0xa9fea9fe) and
 * IPv4-mapped IPv6 are reduced to a canonical address before the check.
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
     * Dotted IPv4, IPv6, the IPv4 embedded in an IPv4-mapped address, or a
     * decimal / hex 32-bit host. Null when the value is a hostname.
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

        return self::ipv4FromIntegerHost($value);
    }

    private static function ipv4FromIntegerHost(string $value): ?string
    {
        if (str_starts_with($value, '0x')) {
            $hex = substr($value, 2);
            if ('' === $hex || strlen($hex) > 8 || !ctype_xdigit($hex)) {
                return null;
            }
            $number = hexdec($hex);
        } elseif (ctype_digit($value)) {
            if (strlen($value) > 10) {
                return null;
            }
            $number = (int) $value;
        } else {
            return null;
        }

        if ($number < 0 || $number > 0xFFFFFFFF) {
            return null;
        }

        $ip = long2ip((int) $number);

        return is_string($ip) ? $ip : null;
    }
}
