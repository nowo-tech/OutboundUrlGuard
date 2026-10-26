<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Dns;

use Closure;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * DNS lookup seam so unit tests can fake A and AAAA answers.
 *
 * Not final: tests replace it. Production lookups run in a short-lived PHP CLI
 * process with an explicit timeout so a stuck resolver cannot pin a
 * FrankenPHP worker. The worker itself never calls ini_set().
 *
 * PHP_BINARY is only the CLI under cli SAPIs; under php-fpm or FrankenPHP it
 * is the server binary, which does not understand `-r`. The CLI is therefore
 * located with PhpExecutableFinder (or configured explicitly). When no CLI is
 * available, or the configured binary cannot run the lookup, the lookup runs
 * in-process: it still works, but is only bounded by the system resolver
 * timeouts (resolv.conf), not by `dns_timeout`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class HostnameDnsLookup
{
    /** PHP CLI used for bounded lookups; null runs lookups in-process. */
    private readonly ?string $phpBinary;

    /**
     * @param float       $timeoutSeconds child-process deadline for one lookup
     * @param string|null $phpBinary      PHP CLI path; null auto-detects it, '' always resolves in-process
     */
    public function __construct(
        private readonly float $timeoutSeconds = 2.0,
        ?string $phpBinary = null,
    ) {
        // PhpExecutableFinder returns false when no CLI exists; '' then means in-process.
        $phpBinary ??= (string) (new PhpExecutableFinder())->find(false);

        $this->phpBinary = '' === $phpBinary ? null : $phpBinary;
    }

    /**
     * Deadline in seconds applied to the DNS child process.
     */
    public function timeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    /**
     * PHP CLI used for bounded lookups, or null when lookups run in-process.
     */
    public function phpBinary(): ?string
    {
        return $this->phpBinary;
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
        $raw = $this->lookupOutput(
            'fwrite(STDOUT, json_encode(@dns_get_record($argv[1], (int) $argv[2])));',
            [$hostname, (string) $type],
            static fn (): mixed => @dns_get_record($hostname, $type),
        );

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
        $raw = $this->lookupOutput(
            'fwrite(STDOUT, json_encode(@gethostbynamel($argv[1])));',
            [$hostname],
            static fn (): mixed => @gethostbynamel($hostname),
        );
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
        $output = $this->execute($command);

        return is_string($output) ? $output : null;
    }

    /**
     * JSON-encoded lookup result: from the PHP CLI child when available, in-process otherwise.
     *
     * Null when the child process hit the deadline (no in-process retry, so the
     * deadline still holds).
     *
     * @param string       $code      PHP code run with `-r`
     * @param list<string> $args      arguments exposed as $argv[1..]
     * @param Closure      $inProcess same lookup without a child process
     */
    protected function lookupOutput(string $code, array $args, Closure $inProcess): ?string
    {
        if (null !== $this->phpBinary) {
            // `--` ends PHP CLI option parsing, so a value starting with `-` stays an argument.
            $output = $this->execute([$this->phpBinary, '-r', $code, '--', ...$args]);
            if (false !== $output) {
                return $output;
            }
        }

        $encoded = json_encode($inProcess());

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @param list<string> $command
     *
     * @return string|false|null output; null on timeout; false when the command could not run
     */
    private function execute(array $command): string|false|null
    {
        $process = new Process($command);
        $process->setTimeout($this->timeoutSeconds);
        $process->setIdleTimeout($this->timeoutSeconds);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException) {
            $process->stop(0);

            return null;
        } catch (RuntimeException) {
            return false;
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
