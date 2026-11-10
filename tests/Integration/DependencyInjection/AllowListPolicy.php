<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Integration\DependencyInjection;

use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlDecision;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuardInterface;

use const PHP_URL_HOST;

/**
 * A host policy behind the port: only one allow-listed host.
 */
final class AllowListPolicy implements OutboundUrlGuardInterface
{
    public function inspect(?string $url, ?bool $resolveDns = null): OutboundUrlDecision
    {
        return 'hooks.example.com' === parse_url((string) $url, PHP_URL_HOST)
            ? OutboundUrlDecision::valid()
            : OutboundUrlDecision::unsafe('Not allow-listed.');
    }

    public function assertSafe(string $url, ?bool $resolveDns = null): OutboundUrlDecision
    {
        return $this->inspect($url, $resolveDns);
    }

    public function httpClientOptions(string $url, ?bool $resolveDns = null): array
    {
        return [];
    }
}
