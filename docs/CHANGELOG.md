# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/nowo-tech/OutboundUrlGuard/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/OutboundUrlGuard/releases/tag/v1.0.0
