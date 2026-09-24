# FrankenPHP worker mode audit (`reset_kernel: false`)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/outbound-url-guard-bundle` (`symfony-bundle`) |
| Audited revision | `v1.0.1` (on top of `v1.0.0` / `8ebe256`) |
| Audit date | 2026-09-24 |
| Method | Manual review of every file under `src/`, DI config, and PHPStan FrankenPHP **classic + worker-strict + hardening** |
| **Verdict** | ✅ **100% compatible** with FrankenPHP worker mode when the kernel is **not** reset between requests |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant used when `reset_kernel` is **false**: the kernel is **not** rebooted between requests, so every shared service, static property, and PHP global that the application mutates can survive from one request to the next.

Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A**, under classic FrankenPHP, and under PHP-FPM. This bundle targets **B**.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `OutboundUrlGuard` is `final readonly`; `HostnameDnsLookup` only holds immutable `timeoutSeconds` |
| Static properties / `static` locals | ✅ | `PrivateNetworkTarget` exposes pure static methods only; no static properties |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | Nothing to reset between requests |
| Request / user / locale captured in services | ✅ | Everything is passed as method arguments |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used at runtime; config is compiled into container parameters |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ | None |
| Resources (files, sockets, cURL) held open | ✅ | DNS uses a short-lived Symfony `Process` per call; stopped on timeout |
| Memory growth across requests | ✅ | No caches or accumulating arrays; DNS results are not stored on the service |
| Blocking I/O and timeouts | ✅ | `dns_timeout` (min `0.1`, default `2.0`) sets Process `setTimeout` + `setIdleTimeout`; worker never calls `ini_set()` |
| Third-party static state | ✅ | Only Symfony DI/Config at compile time; Process is create–run–discard |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker-strict.neon` + `ruleset-hardening.neon` in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard` | yes (public) | none (`readonly` bool flags + DNS seam) | ✅ | ✅ |
| `Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup` | yes | none (readonly timeout only) | ✅ | ✅ |

Value objects (`OutboundUrlDecision`, `OutboundUrlResult`) are created per call and are `readonly` / enum-like; they are never stored on a service.

## Findings

No open findings for worker state leakage or unbounded DNS wait under default and validated config.

### Closed by design — DNS cannot pin a worker indefinitely

- **Where:** `src/Dns/HostnameDnsLookup.php` (`runBounded()`).
- **Worker impact:** a stuck system resolver would otherwise block the PHP worker thread for the OS resolver timeout. Lookups run in a child PHP process bounded by `dns_timeout`; on expiry the child is stopped and the guard fails closed.
- **Config:** `nowo_outbound_url_guard.dns_timeout` minimum `0.1` (Symfony config tree). Callers should still set HttpClient `timeout` / `max_duration` on the actual request.

DNS answers are **not** cached across requests: a cached pin could go stale in a long-lived worker.

## Usage recommendations in worker mode

- No special configuration or reset hook is required for this bundle when `reset_kernel` is false.
- Keep `dns_timeout` below PHP `max_execution_time` and the reverse-proxy write timeout (see [CONFIGURATION.md](CONFIGURATION.md)).
- Always pass the returned `resolve` pin and `max_redirects: 0` to HttpClient on every request; do not store the options array in a service property for reuse — it is only valid for that URL and moment.
- Custom `HostnameDnsLookup` subclasses must stay stateless (or implement `ResetInterface`) and must keep a finite Process timeout to preserve this verdict.

## Re-audit triggers

Re-run this audit when a change adds: mutable properties to `OutboundUrlGuard` or the DNS seam, a result cache, an event listener, use of `$_SERVER` / `$_ENV` at runtime, or removal of the DNS child-process timeout.
