<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Guard;

/**
 * Result of one outbound URL check.
 *
 * `valid` may still carry a DNS pin. Callers that open a socket must pass
 * {@see httpClientOptions()} to HttpClient and set `max_redirects` to 0.
 * A redirect would leave the pin behind.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class OutboundUrlDecision
{
    public const RESULT_VALID = 'valid';

    public const RESULT_INVALID = 'invalid';

    public const RESULT_UNSAFE = 'unsafe';

    /**
     * @param string      $result  one of the RESULT_* values ({@see OutboundUrlResult})
     * @param string      $message empty when the URL is valid
     * @param string|null $pinHost hostname to pin, when DNS resolved
     * @param string|null $pinIp   public address to pin, when DNS resolved
     */
    private function __construct(
        public string $result,
        public string $message,
        public ?string $pinHost = null,
        public ?string $pinIp = null,
    ) {
    }

    /**
     * A URL that may be opened, optionally pinned to one public address.
     *
     * @param string|null $pinHost hostname to pin
     * @param string|null $pinIp   public address to pin
     */
    public static function valid(?string $pinHost = null, ?string $pinIp = null): self
    {
        return new self(OutboundUrlResult::Valid->value, '', $pinHost, $pinIp);
    }

    /**
     * The value is not an http(s) URL.
     *
     * @param string $message reason the URL is invalid
     */
    public static function invalid(string $message): self
    {
        return new self(OutboundUrlResult::Invalid->value, $message);
    }

    /**
     * The URL is http(s) but targets a blocked network.
     *
     * @param string $message reason the URL is unsafe
     */
    public static function unsafe(string $message): self
    {
        return new self(OutboundUrlResult::Unsafe->value, $message);
    }

    /**
     * Whether the URL may be opened.
     */
    public function isValid(): bool
    {
        return self::RESULT_VALID === $this->result;
    }

    /**
     * HttpClient options that pin the host to the resolved public address.
     *
     * @return array{resolve?: array<string, string>}
     */
    public function httpClientOptions(): array
    {
        if (null === $this->pinHost || null === $this->pinIp) {
            return [];
        }

        return ['resolve' => [$this->pinHost => $this->pinIp]];
    }
}
