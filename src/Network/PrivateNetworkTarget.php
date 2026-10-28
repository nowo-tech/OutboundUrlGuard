<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Network;

use function count;
use function defined;
use function function_exists;
use function is_string;
use function ord;
use function strlen;

use const FILTER_FLAG_GLOBAL_RANGE;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const IDNA_CHECK_BIDI;
use const IDNA_CHECK_CONTEXTJ;
use const IDNA_DEFAULT;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_USE_STD3_RULES;
use const INTL_IDNA_VARIANT_UTS46;

/**
 * Private, reserved, and cloud-metadata checks shared by every outbound URL policy.
 *
 * Every address that is not globally reachable (RFC 6890 special-purpose
 * registries, PHP FILTER_FLAG_GLOBAL_RANGE) is blocked, plus multicast,
 * deprecated site-local fec0::/10 and local-use NAT64 64:ff9b:1::/48, which that
 * flag lets through. Alibaba metadata 100.100.100.200 and AWS IPv6 metadata
 * fd00:ec2::254 are matched explicitly. Every IPv4 spelling curl accepts
 * (inet_aton style: decimal, octal, or hex parts, shorthand such as 127.1,
 * 32-bit integers such as 2852039166 or 0xa9fea9fe) and the IPv4 embedded in
 * IPv4-mapped, NAT64 (64:ff9b::/96) and 6to4 (2002::/16) IPv6 are reduced to a
 * canonical dotted address before the check.
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
     * Lowercase ASCII form of a URL host, or null when it cannot be classified safely.
     *
     * Non-ASCII hosts are mapped with IDNA UTS46 using the flags Symfony
     * HttpClient applies to the request host, so `１２７.０.０.１` is classified
     * as the `127.0.0.1` the client would connect to. Without ext-intl or
     * symfony/polyfill-intl-idn the client itself refuses non-ASCII hosts, so
     * they are rejected here too. Percent signs (encoded hosts, IPv6 zone IDs),
     * spaces, and control characters are always rejected.
     *
     * @param string    $host         host as returned by parse_url()
     * @param bool|null $idnAvailable overrides IDNA detection (tests); null detects it
     */
    public static function asciiHost(string $host, ?bool $idnAvailable = null): ?string
    {
        if (1 === preg_match('/[%\x00-\x20\x7F]/', $host)) {
            return null;
        }

        if (1 !== preg_match('/[\x80-\xFF]/', $host)) {
            return strtolower($host);
        }

        $idnAvailable ??= defined('INTL_IDNA_VARIANT_UTS46') && function_exists('idn_to_ascii');
        if (!$idnAvailable) {
            return null;
        }

        $ascii = idn_to_ascii(
            $host,
            IDNA_DEFAULT | IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_NONTRANSITIONAL_TO_ASCII,
            INTL_IDNA_VARIANT_UTS46,
        );
        if (!is_string($ascii) || '' === $ascii || 1 === preg_match('/[^\x21-\x7E]/', $ascii)) {
            return null;
        }

        return strtolower($ascii);
    }

    /**
     * True when the value is a syntactically valid DNS hostname (no trailing dot).
     *
     * Letters, digits, hyphens and underscores (Docker service names); every
     * label is 1-63 characters and does not start or end with a hyphen; at most
     * 253 characters. A leading hyphen would otherwise be read as a command-line
     * option by the DNS child process.
     *
     * @param string $host ASCII hostname without trailing dot
     */
    public static function isValidHostName(string $host): bool
    {
        if ('' === $host || strlen($host) > 253) {
            return false;
        }

        foreach (explode('.', $host) as $label) {
            if (1 !== preg_match('/^[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?$/i', $label)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True for every address that is not globally reachable: loopback, RFC1918,
     * CGNAT 100.64.0.0/10, link-local, unique-local, documentation, benchmarking,
     * multicast, unspecified, reserved, and invalid IPs. IPv4 embedded in mapped,
     * NAT64, or 6to4 IPv6 is classified as the embedded IPv4.
     *
     * @param string $ip address or integer host
     */
    public static function isBlockedIp(string $ip): bool
    {
        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        $canonical = self::canonicalIp($ip) ?? $ip;
        // The range flags reject private / reserved addresses.
        if (false === filter_var(
            $canonical,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        )) {
            return true;
        }

        $packed = (string) @inet_pton($canonical);
        if (4 === strlen($packed)) {
            // 224.0.0.0/4 multicast.
            return 0xE0 === (ord($packed[0]) & 0xF0);
        }

        // ff00::/8 multicast, fec0::/10 deprecated site-local, 64:ff9b:1::/48 local-use NAT64.
        return 0xFF === ord($packed[0])
            || (0xFE === ord($packed[0]) && 0xC0 === (ord($packed[1]) & 0xC0))
            || str_starts_with($packed, "\x00\x64\xFF\x9B\x00\x01");
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

        $packed = (string) @inet_pton($canonical);

        // AWS IPv6 instance metadata (Nitro).
        if (inet_pton('fd00:ec2::254') === $packed) {
            return true;
        }

        // fe80::/10 link-local.
        return 0xFE === ord($packed[0]) && 0x80 === (ord($packed[1]) & 0xC0);
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
     * Dotted IPv4, IPv6, the IPv4 embedded in an IPv4-mapped (::ffff:0:0/96),
     * NAT64 well-known-prefix (64:ff9b::/96) or 6to4 (2002::/16) address, or an
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
            $packed = (string) @inet_pton($value);
            $embedded = match (true) {
                str_starts_with($packed, "\0\0\0\0\0\0\0\0\0\0\xFF\xFF"),
                str_starts_with($packed, "\x00\x64\xFF\x9B\0\0\0\0\0\0\0\0") => substr($packed, 12),
                str_starts_with($packed, "\x20\x02") => substr($packed, 2, 4),
                default => null,
            };
            if (null === $embedded) {
                return $value;
            }

            $ipv4 = @inet_ntop($embedded);

            return is_string($ipv4) ? $ipv4 : null;
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

        $address = array_pop($numbers);
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
