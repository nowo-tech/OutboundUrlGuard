<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Guard;

use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use Nowo\OutboundUrlGuardBundle\Exception\UnsafeOutboundUrlException;
use Nowo\OutboundUrlGuardBundle\Network\PrivateNetworkTarget;

use function in_array;
use function is_array;
use function is_string;

use const DNS_A;
use const DNS_AAAA;
use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;

/**
 * One policy for outbound http(s) URLs.
 *
 * `$allowPrivate` permits loopback, RFC1918, and names such as localhost. Cloud
 * metadata stays blocked either way (169.254.0.0/16, fe80::/10, 100.100.100.200,
 * and the same addresses written as decimal, hex, or IPv4-mapped IPv6).
 *
 * `$resolveDns` resolves hostnames and pins the first public address (IPv4
 * preferred). Leave it false when the host is a Docker service name that must
 * not be looked up.
 *
 * When `$allowPrivate` is true and `$resolveDns` is true, hostnames are still
 * resolved: private answers are accepted, but an answer pointing at cloud
 * metadata is rejected and the first address is pinned so a later lookup cannot
 * rebind to metadata.
 *
 * When `$resolveDns` is false, hostnames are not looked up, so a name whose DNS
 * points at a metadata address is not detected. Only literal addresses and
 * well-known metadata host names are checked in that mode.
 */
final readonly class OutboundUrlGuard
{
    /**
     * @param bool              $allowPrivate when true, loopback and RFC1918 are allowed; metadata stays blocked (DNS answers only when resolving)
     * @param bool              $resolveDns   when true, hostnames are resolved and pinned
     * @param HostnameDnsLookup $dns          DNS seam (timeout lives on the lookup)
     */
    public function __construct(
        private bool $allowPrivate = false,
        private bool $resolveDns = true,
        private HostnameDnsLookup $dns = new HostnameDnsLookup(),
    ) {
    }

    /**
     * Classifies a URL without throwing.
     *
     * @param string|null $url          candidate http(s) URL
     * @param bool|null   $allowPrivate overrides the service default when not null
     * @param bool|null   $resolveDns   overrides the service default when not null
     */
    public function inspect(?string $url, ?bool $allowPrivate = null, ?bool $resolveDns = null): OutboundUrlDecision
    {
        $allowPrivate ??= $this->allowPrivate;
        $resolveDns ??= $this->resolveDns;

        $trimmed = trim((string) $url);
        if ('' === $trimmed) {
            return OutboundUrlDecision::invalid('Outbound URL is not a valid http(s) URL.');
        }

        $parts = parse_url($trimmed);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return OutboundUrlDecision::invalid('Outbound URL is not a valid http(s) URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return OutboundUrlDecision::invalid('Outbound URL is not a valid http(s) URL.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ('' === $host) {
            return OutboundUrlDecision::unsafe('Outbound URL host is not allowed.');
        }

        $canonical = PrivateNetworkTarget::canonicalIp($host);
        if (PrivateNetworkTarget::isCloudMetadataHost($host)
            || (null !== $canonical && PrivateNetworkTarget::isCloudMetadataIp($canonical))
        ) {
            return OutboundUrlDecision::unsafe('Outbound URL must not target cloud metadata.');
        }

        if ($allowPrivate) {
            if (null !== $canonical || !$resolveDns) {
                return OutboundUrlDecision::valid();
            }

            return $this->resolveAndPin($host, true);
        }

        if (PrivateNetworkTarget::isBlockedHostName($host)) {
            return OutboundUrlDecision::unsafe('Outbound URL host is not allowed.');
        }

        if (null !== $canonical) {
            if (PrivateNetworkTarget::isBlockedIp($canonical)) {
                return OutboundUrlDecision::unsafe('Outbound URL must not target a private address.');
            }

            return OutboundUrlDecision::valid();
        }

        if (!$resolveDns) {
            return OutboundUrlDecision::valid();
        }

        return $this->resolveAndPin($host, false);
    }

    /**
     * Rejects an invalid or unsafe URL.
     *
     * @param string    $url          candidate http(s) URL
     * @param bool|null $allowPrivate overrides the service default when not null
     * @param bool|null $resolveDns   overrides the service default when not null
     *
     * @throws UnsafeOutboundUrlException when the URL is invalid or unsafe
     */
    public function assertSafe(string $url, ?bool $allowPrivate = null, ?bool $resolveDns = null): OutboundUrlDecision
    {
        $decision = $this->inspect($url, $allowPrivate, $resolveDns);
        if (!$decision->isValid()) {
            throw new UnsafeOutboundUrlException($decision->message);
        }

        return $decision;
    }

    /**
     * Asserts the URL and returns HttpClient resolve options when a pin exists.
     *
     * @param string    $url          candidate http(s) URL
     * @param bool|null $allowPrivate overrides the service default when not null
     * @param bool|null $resolveDns   overrides the service default when not null
     *
     * @return array{resolve?: array<string, string>}
     *
     * @throws UnsafeOutboundUrlException when the URL is invalid or unsafe
     */
    public function httpClientOptions(string $url, ?bool $allowPrivate = null, ?bool $resolveDns = null): array
    {
        return $this->assertSafe($url, $allowPrivate, $resolveDns)->httpClientOptions();
    }

    /**
     * Resolves a hostname and pins the first acceptable address (IPv4 preferred).
     *
     * @param string $host         lowercase hostname
     * @param bool   $allowPrivate when true, private answers pass; metadata answers never do
     */
    private function resolveAndPin(string $host, bool $allowPrivate): OutboundUrlDecision
    {
        $candidates = [];

        $aRecords = $this->dns->dnsGetRecord($host, DNS_A);
        if (is_array($aRecords)) {
            foreach ($aRecords as $row) {
                if (isset($row['ip']) && is_string($row['ip']) && '' !== $row['ip']) {
                    $candidates[] = $row['ip'];
                }
            }
        }

        $aaaaRecords = $this->dns->dnsGetRecord($host, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $row) {
                if (isset($row['ipv6']) && is_string($row['ipv6']) && '' !== $row['ipv6']) {
                    $candidates[] = $row['ipv6'];
                }
            }
        }

        if ([] === $candidates) {
            $fallback = $this->dns->hostByNameL($host);
            if (is_array($fallback)) {
                $candidates = $fallback;
            }
        }

        if ([] === $candidates) {
            return OutboundUrlDecision::unsafe('Outbound URL host could not be resolved.');
        }

        $acceptedIps = [];
        foreach (array_values(array_unique($candidates)) as $ip) {
            if (!$allowPrivate && (PrivateNetworkTarget::isBlockedIp($ip) || PrivateNetworkTarget::isCloudMetadataIp($ip))) {
                return OutboundUrlDecision::unsafe('Outbound URL resolves to a private address.');
            }
            if (PrivateNetworkTarget::isCloudMetadataIp($ip)) {
                return OutboundUrlDecision::unsafe('Outbound URL must not target cloud metadata.');
            }
            $acceptedIps[] = $ip;
        }

        $pinIp = $acceptedIps[0];
        foreach ($acceptedIps as $ip) {
            if (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $pinIp = $ip;
                break;
            }
        }

        return OutboundUrlDecision::valid($host, $pinIp);
    }
}
