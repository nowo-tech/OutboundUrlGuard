# Runtime private-URL toggle

**Package:** `nowo-tech/outbound-url-guard-bundle`  
**Status:** Implemented (1.2.0)  
**Last updated:** 2026-10-13  
**Origin:** Symfony Beacon spec 137 (FR-006, KIT-GUARD-RESOLVER)

## Problem

`allow_private` is a compiled container parameter. Applications that store "allow private URLs" as an operator setting (database, feature flag) had to wrap the guard and build `new OutboundUrlGuard(allowPrivate: $settings->allowPrivateUrls(), ...)` on each call, duplicating the DNS wiring.

## User scenarios

### US-01 — Operator toggles private URLs without a redeploy

- **Given** `allow_private_resolver` points at a host service whose `allowPrivate()` returns false
- **When** the caller asks `RuntimeOutboundUrlGuard::inspect('http://127.0.0.1/hook')`
- **Then** the decision is unsafe
- **And** after the setting flips to true, the next call on the same service is valid

### US-02 — No resolver configured

- **Given** `allow_private_resolver` is null (default)
- **When** the caller uses `RuntimeOutboundUrlGuard` or `OutboundUrlGuardFactory`
- **Then** the static `allow_private` value applies, exactly like `OutboundUrlGuard`

### US-03 — Metadata stays blocked

- **Given** the resolver returns true
- **When** the caller inspects `http://169.254.169.254/latest/meta-data`, its decimal form, `fe80::/10`, `metadata.google.internal`, or a hostname whose DNS answer is metadata (`resolve_dns: true`)
- **Then** the decision is unsafe

## Functional requirements

- **FR-RES-001**: `Guard\AllowPrivateResolverInterface::allowPrivate(): bool`.
- **FR-RES-002**: `Guard\ConfiguredAllowPrivateResolver` returns the static `allow_private` value; it is the default resolver.
- **FR-RES-003**: `Guard\OutboundUrlGuardFactory::create(): OutboundUrlGuard` reads the resolver on every call and passes `resolve_dns` and the shared `HostnameDnsLookup` service (so `dns_timeout` / `php_binary` apply).
- **FR-RES-004**: `Guard\RuntimeOutboundUrlGuard` exposes `inspect(?string $url, ?bool $resolveDns = null)`, `assertSafe(string $url, ?bool $resolveDns = null)` and `httpClientOptions(string $url, ?bool $resolveDns = null)` with the same semantics as `OutboundUrlGuard`; each call reads the resolver once.
- **FR-RES-005**: Config `allow_private_resolver` (service id, optional leading `@`, default null). The extension aliases the private id `nowo_outbound_url_guard.allow_private_resolver` to it, or to `ConfiguredAllowPrivateResolver` when null. Empty or non-string ids fail configuration.
- **FR-RES-006**: `OutboundUrlGuard`, `OutboundUrlGuardFactory` and `RuntimeOutboundUrlGuard` are public, autowirable services. `OutboundUrlGuard` keeps the compiled `allow_private` value (no behaviour change).
- **FR-RES-007**: The bundle does not alias `AllowPrivateResolverInterface` itself, so a host's own autowiring of the interface is not overridden.

## Non-goals

- Caching the resolver answer (the host decides; the bundle reads it per call).
- Overriding `allowPrivate` per call on `RuntimeOutboundUrlGuard` (use `OutboundUrlGuard` for explicit per-call values).

## Tests

- `tests/Unit/Guard/RuntimeOutboundUrlGuardTest.php`: static default, toggle between calls (resolver call count), metadata blocked when the resolver returns true, DNS pin and `resolveDns` override.
- `tests/Integration/DependencyInjection/AllowPrivateResolverIntegrationTest.php`: default wiring, custom resolver service id toggled at runtime, invalid ids rejected.
