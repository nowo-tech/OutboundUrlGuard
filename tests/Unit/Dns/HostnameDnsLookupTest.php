<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Unit\Dns;

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
        self::assertNotFalse($lookup->dnsGetRecord('localhost', DNS_A));
        self::assertNotFalse($lookup->hostByNameL('localhost'));
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
        $invalid = new class extends HostnameDnsLookup {
            public ?string $raw = '123';

            public function runBounded(array $command): ?string
            {
                return $this->raw;
            }
        };
        self::assertFalse($invalid->dnsGetRecord('example.test', DNS_A));
        self::assertFalse($invalid->hostByNameL('example.test'));

        $empty = new class extends HostnameDnsLookup {
            public ?string $raw = null;

            public function runBounded(array $command): ?string
            {
                return $this->raw;
            }
        };
        self::assertFalse($empty->dnsGetRecord('example.test', DNS_A));

        $mixed = new class extends HostnameDnsLookup {
            public ?string $raw = '["", 1, "127.0.0.1"]';

            public function runBounded(array $command): ?string
            {
                return $this->raw;
            }
        };
        self::assertSame(['127.0.0.1'], $mixed->hostByNameL('example.test'));
    }
}
