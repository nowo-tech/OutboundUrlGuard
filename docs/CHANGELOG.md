# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `OutboundUrlGuard` with `allowPrivate` and `resolveDns`. Metadata stays blocked when private URLs are allowed. DNS answers can be returned as an HttpClient `resolve` pin (IPv4 preferred).
- `dns_timeout` (default 2 seconds) stops the DNS child process (`Symfony Process` timeout and idle timeout).
- CI fails on direct Symfony deprecations (`SYMFONY_DEPRECATIONS_HELPER=max[direct]=0`).
- `PrivateNetworkTarget` for shared IP and hostname checks, including decimal, hex, and IPv4-mapped metadata addresses.
- `OutboundUrlResult` for the closed set of decisions (`valid`, `invalid`, `unsafe`).
