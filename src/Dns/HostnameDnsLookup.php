<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Dns;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

use function is_array;
use function is_string;
use function json_decode;

use const PHP_BINARY;

/**
 * DNS lookup seam so unit tests can fake A and AAAA answers.
 *
 * Not final: tests replace it. Production lookups run in a short-lived PHP
 * process with an explicit timeout so a stuck resolver cannot pin a
 * FrankenPHP worker. The worker itself never calls ini_set().
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class HostnameDnsLookup
{
    /**
     * @param float $timeoutSeconds child-process deadline for one lookup
     */
    public function __construct(
        private readonly float $timeoutSeconds = 2.0,
    ) {
    }

    /**
     * Deadline in seconds applied to the DNS child process.
     */
    public function timeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    /**
     * Resolve A or AAAA records for a hostname.
     *
     * @param string $hostname host to resolve
     * @param int    $type     DNS_A or DNS_AAAA
     *
     * @return array<int, array<string, mixed>>|false
     */
    public function dnsGetRecord(string $hostname, int $type): array|false
    {
        $raw = $this->runBounded([
            PHP_BINARY,
            '-r',
            'fwrite(STDOUT, json_encode(@dns_get_record($argv[1], (int) $argv[2])));',
            $hostname,
            (string) $type,
        ]);

        return $this->decodeList($raw);
    }

    /**
     * IPv4 fallback when dns_get_record returns nothing.
     *
     * @param string $hostname host to resolve
     *
     * @return list<string>|false
     */
    public function hostByNameL(string $hostname): array|false
    {
        $raw = $this->runBounded([
            PHP_BINARY,
            '-r',
            'fwrite(STDOUT, json_encode(@gethostbynamel($argv[1])));',
            $hostname,
        ]);
        $decoded = $this->decodeList($raw);
        if (!is_array($decoded)) {
            return false;
        }

        $ips = [];
        foreach ($decoded as $ip) {
            if (is_string($ip) && '' !== $ip) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }

    /**
     * Run a command and stop it when the DNS deadline expires.
     *
     * @param list<string> $command
     */
    public function runBounded(array $command): ?string
    {
        $process = new Process($command);
        $process->setTimeout($this->timeoutSeconds);
        $process->setIdleTimeout($this->timeoutSeconds);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException) {
            $process->stop(0);

            return null;
        } catch (ProcessFailedException) {
            return null;
        }

        return $process->getOutput();
    }

    /**
     * @return array<int, mixed>|false
     */
    private function decodeList(?string $raw): array|false
    {
        if (null === $raw || '' === $raw || 'false' === $raw || 'null' === $raw) {
            return false;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : false;
    }
}
