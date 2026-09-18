# Baseline specification — Outbound URL Guard

**Package:** `nowo-tech/outbound-url-guard-bundle`  
**Status:** Implemented (pre-1.0.0)  
**Last updated:** 2026-09-18

## Product summary

Before an application opens an outbound `http` or `https` URL, this bundle rejects private networks and cloud metadata. Hostnames can be resolved once and pinned so a later DNS answer cannot steer the TCP connection. The bundle does not send the request.

Config root: `nowo_outbound_url_guard`. Main surface: `OutboundUrlGuard`.

## User scenarios

### US-01 — Block a stored webhook that points at metadata

- **Given** `allow_private` is true or false
- **When** the caller inspects `http://169.254.169.254/latest/meta-data` or `http://2852039166/latest/meta-data`
- **Then** the decision is unsafe

### US-02 — Allow a Docker hub name

- **Given** `resolve_dns` is false
- **When** the caller inspects `http://mercure/.well-known/mercure`
- **Then** the decision is valid and no DNS lookup runs
- **And** a literal private IP is still unsafe

### US-03 — Pin a public hostname

- **Given** DNS returns `1.1.1.1` and no private address
- **When** the caller asks for `httpClientOptions()`
- **Then** the options contain `resolve[host] = 1.1.1.1` (IPv4 preferred when both families are public)

### US-04 — Bound DNS

- **Given** `dns_timeout` is `2`
- **When** a lookup runs
- **Then** the lookup child process is stopped if it is still running after 2 seconds

## Scope

### In scope

- Scheme allow-list `http` / `https`
- Private, reserved, and blocked hostnames
- Cloud metadata, including decimal, hex, and IPv4-mapped IPv6
- Optional DNS pin and `dns_timeout`
- Symfony config tree with safe defaults

### Explicit non-goals

- Performing the HTTP request
- Following redirects
- TLS verification policy
- Admin UI, translations, Twig, database

## Functional requirements

### Bundle and dependency injection

- **FR-BUNDLE-001**: `NowoOutboundUrlGuardBundle` exposes `NowoOutboundUrlGuardExtension`.
- **FR-DI-001**: The extension loads `services.yaml`, processes the config tree, and publishes `allow_private`, `resolve_dns`, and `dns_timeout`.
- **FR-DI-002**: Unknown configuration keys fail compilation. Defaults deny private URLs and enable DNS with a 2 second timeout.

### Decision

- **FR-DECISION-001**: `OutboundUrlResult` is the closed set `valid` / `invalid` / `unsafe`.
- **FR-DECISION-002**: `OutboundUrlDecision` carries the result, a message, and an optional pin. `httpClientOptions()` returns `resolve` only when both pin fields are set.
- **FR-DECISION-003**: `UnsafeOutboundUrlException` is an `InvalidArgumentException` thrown by `assertSafe()` and `httpClientOptions()`.

### Guard policy

- **FR-GUARD-001**: Empty values and non-http(s) URLs are invalid.
- **FR-GUARD-002**: Cloud metadata hosts and IPs are unsafe even when private URLs are allowed.
- **FR-GUARD-003**: With `allow_private`, other targets are valid and DNS is skipped.
- **FR-GUARD-004**: Blocked hostnames and private/reserved IPs are unsafe when private URLs are not allowed.
- **FR-GUARD-005**: With `resolve_dns` false, a non-literal hostname that is not blocked is valid without DNS.
- **FR-GUARD-006**: DNS that returns any blocked address is unsafe. A public answer is pinned, IPv4 preferred. No answer is unsafe.

### DNS and network

- **FR-DNS-001**: `HostnameDnsLookup` runs `dns_get_record` and `gethostbynamel` in a child PHP process with `dns_timeout` (`setTimeout` and `setIdleTimeout`) and stops the process on expiry. The class stays extensible for tests. The worker does not call `ini_set()`.
- **FR-NET-001**: `PrivateNetworkTarget` canonicalizes dotted IPv4, IPv6, IPv4-mapped IPv6, and decimal/hex 32-bit hosts before private and metadata checks.

## Success criteria

- **SC-001**: Every production file under `src/` is listed in `code-inventory.md` (**10/10**).
- **SC-002**: `make test` covers the FR-* paths above.
- **SC-003**: PHP statement coverage stays at or above 99% (`make test-coverage`).

## Validation commands

```bash
make test
make phpstan
make test-coverage
```
