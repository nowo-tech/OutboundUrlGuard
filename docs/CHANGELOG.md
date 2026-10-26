# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.3.0] - 2026-10-26](#130---2026-10-26)
- [[1.2.1] - 2026-10-21](#121---2026-10-21)
- [[1.2.0] - 2026-10-13](#120---2026-10-13)
- [[1.1.0] - 2026-10-13](#110---2026-10-13)
- [[1.0.4] - 2026-10-13](#104---2026-10-13)
- [[1.0.3] - 2026-10-09](#103---2026-10-09)
- [[1.0.2] - 2026-09-27](#102---2026-09-27)
- [[1.0.1] - 2026-09-24](#101---2026-09-24)
- [[1.0.0] - 2026-09-18](#100---2026-09-18)

## [Unreleased]

## [1.3.0] - 2026-10-26

SSRF hardening from the 2026-10-26 security re-audit: DNS pin covers trailing-dot hosts, non-global ranges blocked, DNS child argv hardened, AWS IPv6 metadata, disguised literals rejected. See [UPGRADING](UPGRADING.md#to-130).

Security hardening from the 2026-10-26 static re-audit. See [UPGRADING](UPGRADING.md#unreleased) for URLs that are now rejected.

### Security

- `docs/SECURITY.md`: forward proxies (`https_proxy`, the `proxy` option) bypass the DNS pin — new caller duty.
- **M1** DNS pin now covers the trailing-dot spelling: `httpClientOptions()` returns `resolve` entries for `host` and `host.`. HttpClient keeps the trailing dot in the request host, so `http://rebind.attacker.tld./` used to miss the pin and re-resolve (DNS rebinding).
- **L1** Every non-globally-reachable address is private (`FILTER_FLAG_GLOBAL_RANGE`): CGNAT `100.64.0.0/10`, benchmarking `198.18.0.0/15`, documentation ranges, `2001::/23`, plus multicast `224.0.0.0/4` / `ff00::/8`, site-local `fec0::/10`, and local-use NAT64 `64:ff9b:1::/48`. DNS answers are classified on their canonical form, so an AAAA answer `::ffff:127.0.0.1` is rejected.
- **L2** The DNS child process receives the hostname after `--`, so a host starting with `-` is no longer parsed as a PHP CLI option (which made the child fail and the lookup fall back to the in-process resolver, unbounded by `dns_timeout`). Invalid hostname syntax is rejected before any lookup.
- **L3** AWS IPv6 instance metadata `fd00:ec2::254` is cloud metadata (blocked in every mode). NAT64 `64:ff9b::/96` and 6to4 `2002::/16` addresses are classified as their embedded IPv4.
- **L4** Hosts are IDNA/UTS46-mapped before classification (`http://１２７.０.０.１/` is `127.0.0.1`); without ext-intl or `symfony/polyfill-intl-idn` non-ASCII hosts are rejected. Hosts containing `%` (percent-encoding, IPv6 zone IDs) are rejected.

### Fixed

- `PrivateNetworkTarget::isBlockedIp()` classifies IPv4-mapped, NAT64, and 6to4 IPv6 by the embedded IPv4.
- New helpers `PrivateNetworkTarget::asciiHost()` and `PrivateNetworkTarget::isValidHostName()`.
- `docs/SECURITY.md`: attack surface, caller duties (rate-limit submissions, pin every host spelling with non-Symfony clients), and the 2026-10-26 audit.
- Dev: `symfony/polyfill-intl-idn` in `require-dev` so the IDNA path is tested without ext-intl.

[1.3.0]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.3.0

## [1.2.1] - 2026-10-21

Dependency refresh, wave-1 docs, PHPStan CI job, release-check starts with ensure-up. See [UPGRADING](UPGRADING.md#to-121).

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- Documentation: wave-1 REQ compliance — README documentation list order (worker audit under Additional documentation), Engram link fixed, TOCs for long docs, canonical baseline inventory 14/14 with spec-section column.
- CI: new `phpstan` job runs `composer phpstan` on every push and pull request (REQ-CS-006).
- `release-check` runs sequentially and starts with `ensure-up` (REQ-MAKE-002).

### Fixed

- `.scripts/check-no-cursor-coauthor.sh` also runs inside git worktrees (`.git` file instead of directory).
- `.gitignore`: ignore the local SonarQube coverage directory `/.sonar-coverage/`.

[1.2.1]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.2.1

## [1.2.0] - 2026-10-13

### Added

- `AllowPrivateResolverInterface` (`allowPrivate(): bool`) for a runtime "allow private URLs" switch, such as an operator setting.
- `OutboundUrlGuardFactory::create(): OutboundUrlGuard` reads the resolver on every call and reuses the shared `HostnameDnsLookup` service and `resolve_dns`.
- `RuntimeOutboundUrlGuard` service: `inspect()`, `assertSafe()` and `httpClientOptions()` with the guard's semantics, `allowPrivate` taken from the resolver on every call. Cloud metadata stays blocked whatever the resolver returns.
- Config `allow_private_resolver` (service id, default `null` = the static `allow_private` value). Default resolver: `ConfiguredAllowPrivateResolver`.

### Changed

- `OutboundUrlGuard` is unchanged and keeps the compiled `allow_private` value.
- Development: dev lock refreshed (`nowo-tech/phpstan-frankenphp` 1.3.0); Rector `RecastingRemovalRector` cleanup in `PrivateNetworkTarget` (no behaviour change).

[1.2.0]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.2.0

## [1.1.0] - 2026-10-13

### Security

- IPv4 literals in every form curl accepts (`127.1`, `0177.0.0.1`, `0x7f.0.0.1`, `169.254.43518`, `2130706433`) are normalized before classification, so they no longer bypass the private-network and cloud-metadata checks.
- With `allow_private: true` and `resolve_dns: true`, hostnames are now resolved: answers pointing at cloud metadata are rejected and the first answer is pinned. Previously DNS was skipped in that mode, so a hostname resolving to `169.254.169.254` passed.

### Fixed

- DNS lookups no longer fail under php-fpm or FrankenPHP: the child process uses the PHP CLI found by `PhpExecutableFinder` instead of `PHP_BINARY` (the server binary there), and falls back to an in-process lookup when no CLI is usable. Previously every hostname was reported as "could not be resolved" with the default `resolve_dns: true`.

### Changed

- New `php_binary` option (default `null` = auto-detect; `''` = always resolve in-process). In-process lookups are bounded by the system resolver timeouts, not `dns_timeout`.
- Docs: `resolve_dns: false` cannot detect hostnames that resolve to metadata or private addresses; the limitation is now documented.

[1.1.0]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.1.0

## [1.0.4] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.

[1.0.4]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.0.4

## [1.0.3] - 2026-10-09

### Dependencies

- `igor-php/igor-php` require-dev constraint bumped to `^0.10.0` (Dependabot); `nowo-tech/phpstan-frankenphp` 1.2.x and `phpstan/phpstan-phpunit` Dependabot bumps.
- Dev lock refreshed: Symfony 7.4.20, PHPStan 2.3.1, `phpstan/phpstan-symfony` 2.1.0, Rector 2.7.0, PHPUnit 11.5.57, `nowo-tech/phpstan-frankenphp` 1.2.3.

[1.0.3]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.0.3

## [1.0.2] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.0.2]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.0.2

## [1.0.1] - 2026-09-24

### Added

- FrankenPHP worker audit for `reset_kernel: false`: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md). Verdict: 100% compatible (no per-request state; DNS bounded by child process).
- Spec requirements `FR-WORKER-001` … `FR-WORKER-004` for long-lived workers without kernel reset.

### Changed

- PHPStan now includes FrankenPHP `ruleset-worker-strict` and `ruleset-hardening` (in addition to classic + worker) so CI gates worker-safe and hardened code.

## [1.0.0] - 2026-09-18

### Added

- Initial public release of **Outbound URL Guard** (`nowo-tech/outbound-url-guard-bundle`).
- `OutboundUrlGuard` with `allowPrivate` and `resolveDns`. Metadata stays blocked when private URLs are allowed. DNS answers can be returned as an HttpClient `resolve` pin (IPv4 preferred).
- `dns_timeout` (default 2 seconds) stops the DNS child process (`Symfony Process` timeout and idle timeout).
- `PrivateNetworkTarget` for shared IP and hostname checks, including decimal, hex, and IPv4-mapped metadata addresses.
- `OutboundUrlResult` for the closed set of decisions (`valid`, `invalid`, `unsafe`).

### Security

- Defaults deny private and reserved targets. Cloud metadata stays blocked even when `allow_private` is true.
- DNS lookup fails closed and is bounded by `dns_timeout`. The hostname is passed as a process argument, not interpolated into a shell command.
- AI security audit recorded **2026-09-18**: Pass (good), overall risk Low. No open Critical or High findings.
- CI fails on direct Symfony deprecations (`SYMFONY_DEPRECATIONS_HELPER=max[direct]=0`) and runs `composer audit --locked`.

[Unreleased]: https://github.com/nowo-tech/OutboundUrlGuard/compare/v1.3.0...HEAD
[1.0.1]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.0.0
