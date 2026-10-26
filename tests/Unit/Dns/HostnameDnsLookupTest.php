<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Unit\Dns;

use Closure;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use PHPUnit\Framework\TestCase;

use const DNS_A;
use const PHP_BINARY;

final class HostnameDnsLookupTest extends TestCase
{
    public function testNativeLookupsReturnArrayOrFalse(): void
    {
        $lookup = new HostnameDnsLookup();

        self::assertSame(2.0, $lookup->timeoutSeconds());
        self::assertNotNull($lookup->phpBinary());
        self::assertNotFalse($lookup->dnsGetRecord('localhost', DNS_A));
        self::assertNotFalse($lookup->hostByNameL('localhost'));
    }

    /**
     * Under php-fpm or FrankenPHP, PHP_BINARY is the server binary and rejects `-r`.
     * Lookups must fall back in-process instead of reporting every host as unresolved.
     */
    public function testNonCliBinaryFallsBackToInProcessLookups(): void
    {
        foreach (['/bin/false', '/nonexistent/sbin/php-fpm'] as $binary) {
            $lookup = new HostnameDnsLookup(2.0, $binary);

            self::assertSame($binary, $lookup->phpBinary());
            self::assertNotFalse($lookup->dnsGetRecord('localhost', DNS_A), $binary);
            self::assertContains('127.0.0.1', (array) $lookup->hostByNameL('localhost'), $binary);
        }
    }

    public function testEmptyBinaryResolvesInProcess(): void
    {
        $lookup = new HostnameDnsLookup(2.0, '');

        self::assertNull($lookup->phpBinary());
        self::assertContains('127.0.0.1', (array) $lookup->hostByNameL('localhost'));
    }

    public function testStuckChildIsNotRetriedInProcess(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'dns-stuck-');
        self::assertIsString($script);
        file_put_contents($script, "#!/bin/sh\nsleep 5\n");
        chmod($script, 0o755);

        try {
            $lookup = new HostnameDnsLookup(0.3, $script);
            $started = microtime(true);

            self::assertFalse($lookup->hostByNameL('localhost'));
            self::assertLessThan(2.0, microtime(true) - $started);
        } finally {
            unlink($script);
        }
    }

    /**
     * Sec-004 L2: the lookup arguments follow `--`, so a value starting with `-`
     * is not parsed as a PHP CLI option (which made the child fail and the
     * unbounded in-process fallback run).
     */
    public function testLookupArgumentsFollowDoubleDash(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'dns-argv-');
        self::assertIsString($script);
        file_put_contents($script, "#!/bin/sh\nprintf '[\"%s\",\"%s\",\"%s\"]' \"\$1\" \"\$3\" \"\$4\"\n");
        chmod($script, 0o755);

        try {
            $lookup = new HostnameDnsLookup(2.0, $script);

            self::assertSame(['-r', '--', '-h'], $lookup->hostByNameL('-h'));
        } finally {
            unlink($script);
        }
    }

    public function testChildProcessIsStoppedWhenTheDeadlineExpires(): void
    {
        $lookup = new HostnameDnsLookup(0.3);
        $started = microtime(true);

        self::assertNull($lookup->runBounded([PHP_BINARY, '-r', 'sleep(5);']));
        self::assertLessThan(2.0, microtime(true) - $started);
        self::assertNull($lookup->runBounded([PHP_BINARY, '-r', 'exit(1);']));
    }

    public function testDecodeRejectsNonListsAndSkipsBlankAddresses(): void
    {
        $fake = new class extends HostnameDnsLookup {
            public ?string $raw = null;

            protected function lookupOutput(string $code, array $args, Closure $inProcess): ?string
            {
                return $this->raw;
            }
        };

        $fake->raw = '123';
        self::assertFalse($fake->dnsGetRecord('example.test', DNS_A));
        self::assertFalse($fake->hostByNameL('example.test'));

        $fake->raw = null;
        self::assertFalse($fake->dnsGetRecord('example.test', DNS_A));

        $fake->raw = '["", 1, "127.0.0.1"]';
        self::assertSame(['127.0.0.1'], $fake->hostByNameL('example.test'));
    }
}
