# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.2.1](#to-121)
- [To 1.2.0](#to-120)
- [To 1.1.0](#to-110)
- [To 1.0.4](#to-104)
- [To 1.0.3](#to-103)
- [To 1.0.2](#to-102)
- [From 1.0.0 to 1.0.1](#from-100-to-101)
- [From nothing to 1.0.0](#from-nothing-to-100)

## Unreleased

From **1.2.1** — SSRF hardening (2026-10-26 security re-audit). No API changes, but some URLs that 1.2.1 accepted are now rejected (`unsafe` / `UnsafeOutboundUrlException`). Review them if you store webhook URLs:

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

- **Behaviour change — now rejected with `allow_private: false`:** CGNAT `100.64.0.0/10` (except Alibaba metadata, already blocked), multicast `224.0.0.0/4` and `ff00::/8`, benchmarking `198.18.0.0/15`, documentation ranges (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`), `192.0.0.0/24`, `2001::/23`, `100::/64`, `fec0::/10`, `64:ff9b:1::/48`, and NAT64 / 6to4 / IPv4-mapped IPv6 whose embedded IPv4 is private — as literals or as DNS answers. With `allow_private: true` they still pass.
- **Behaviour change — rejected in every mode:** AWS IPv6 metadata `fd00:ec2::254` (and NAT64 spellings of metadata); hosts containing `%` (percent-encoded hosts, IPv6 zone IDs); hosts that are not valid hostname syntax (a label starting or ending with `-`, an empty label such as `a..b` or `host..`, a label over 63 characters, or a name over 253 characters); non-ASCII hosts when neither ext-intl nor `symfony/polyfill-intl-idn` is installed. Underscores (Docker service names) are still accepted.
- Non-ASCII hosts are classified by their IDNA/UTS46 ASCII form, so a fullwidth or ideographic spelling of a private literal is now caught. Install ext-intl or `symfony/polyfill-intl-idn` if you need IDN hosts (Symfony HttpClient needs one of them for such hosts anyway).
- `httpClientOptions()` now returns two `resolve` entries (`host` and `host.`) for the same pinned IP. If you assert on the exact array, update the expectation. If you read `pinHost` / `pinIp` to configure a non-Symfony client, pin both spellings too.

## To 1.2.1

From **1.2.0**:

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

## To 1.2.0

From **1.1.0** — runtime private-URL toggle (`AllowPrivateResolverInterface`, `OutboundUrlGuardFactory`, `RuntimeOutboundUrlGuard`).

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

- No breaking changes. `OutboundUrlGuard` and existing config behave as before.
- Optional: if your app wraps the guard to read "allow private URLs" from a setting, implement `AllowPrivateResolverInterface` on that settings service, set `nowo_outbound_url_guard.allow_private_resolver: App\Settings\YourService`, inject `RuntimeOutboundUrlGuard`, and delete the wrapper. `assertSafeHttpUrl($url)` becomes `assertSafe($url)` and `httpClientOptionsForUrl($url)` becomes `httpClientOptions($url)` (both still throw `UnsafeOutboundUrlException`, an `InvalidArgumentException`).

## To 1.1.0

From **1.0.4** — DNS lookups work under FPM/FrankenPHP; metadata answers blocked with allow_private; inet_aton IPv4 forms normalized.

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

- DNS lookups no longer depend on `PHP_BINARY` (which is not the CLI under FPM/FrankenPHP); new `php_binary` option (`null` = auto-detect, `''` = always in-process).
- With `allow_private: true` and `resolve_dns: true`, hostnames are now resolved and pinned: unresolvable names (previously accepted) and names resolving to cloud metadata are rejected. Use `resolve_dns: false` for the old no-lookup behaviour (which cannot detect metadata answers).
- Shorthand/octal/hex/decimal IPv4 literals (`127.1`, `0177.0.0.1`, `0x7f.0.0.1`, `2130706433`) are now classified as private/metadata instead of passing.

## To 1.0.4

From **1.0.3** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.0.3

From **1.0.2** — dependency refresh.

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.0.2

From **1.0.1** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/outbound-url-guard-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.0.0 to 1.0.1

No breaking API or config changes. Defaults stay the same:

| Key | Default |
| --- | --- |
| `allow_private` | `false` |
| `resolve_dns` | `true` |
| `dns_timeout` | `2` |

What changed for integrators:

- Documented **100% FrankenPHP worker compatibility** when `reset_kernel` is false (no bundle reset hook required). See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
- Dev dependency / CI: PHPStan enables FrankenPHP **worker-strict** and **hardening** rulesets. Application code is unchanged.

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

## From nothing to 1.0.0

First release. There is no previous Packagist version to migrate from.

```bash
composer require nowo-tech/outbound-url-guard-bundle
```

With Symfony Flex, the recipe registers the bundle and copies `config/packages/nowo_outbound_url_guard.yaml`.

Defaults:

| Key | Default |
| --- | --- |
| `allow_private` | `false` |
| `resolve_dns` | `true` |
| `dns_timeout` | `2` |

After `httpClientOptions()` accepts a URL, set `max_redirects` to `0` on the HTTP client. The pin covers the first host only. See [Usage](USAGE.md).

No public config key has been removed. Future removals will keep a deprecated alias for at least one minor while the package is pre-2.0, and will be listed here.
