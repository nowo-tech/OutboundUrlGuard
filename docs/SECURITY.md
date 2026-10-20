# Security

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threat model](#threat-model)
- [Mitigations](#mitigations)
- [What the caller must still do](#what-the-caller-must-still-do)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [Dependencies and updates](#dependencies-and-updates)
- [Permissions and exposure](#permissions-and-exposure)
- [Reporting](#reporting)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)
- [AI security audit](#ai-security-audit)

## Scope

This bundle decides whether an outbound `http` or `https` URL is safe to open. It does not send the request, terminate TLS, follow redirects, or store credentials.

## Attack surface

- The URL string passed to `OutboundUrlGuard` (often a webhook or callback chosen by a user).
- Bundle config: `allow_private`, `allow_private_resolver`, `resolve_dns`, `dns_timeout`, `php_binary`.
- The host `AllowPrivateResolverInterface` service, when configured (its answer switches private targets on for `RuntimeOutboundUrlGuard` / `OutboundUrlGuardFactory`).
- DNS answers for hostnames when `resolve_dns` is true.
- No HTTP routes, forms, CLI commands, or admin UI.

## Threat model

| Threat | Risk |
| --- | --- |
| SSRF to loopback, RFC1918, or link-local | High if a user-controlled URL is fetched |
| Cloud metadata (`169.254.169.254`, Alibaba `100.100.100.200`, `fe80::/10`) including decimal, octal, hex, shorthand (`169.254.43518`), and IPv4-mapped forms | High |
| DNS rebinding after a check | High if the TCP connection is not pinned |
| Redirect to a second host | High if `max_redirects` is left at the client default |
| Resolver hang | Medium under FrankenPHP / FPM (one stuck DNS call pins a worker) |
| XSS, CSRF, SQL injection, path traversal, deserialization | Not applicable: no HTML, no database, no file paths from the URL |

## Mitigations

- Schemes other than `http` and `https` are invalid.
- Private, reserved, and blocked hostnames are rejected unless `allow_private` is true.
- IPv4 literals are normalized the way curl and `inet_aton()` read them (`127.1`, `0177.0.0.1`, `0x7f.0.0.1`, `2130706433`) before they are classified.
- Cloud metadata literals and names stay blocked in both modes. With `resolve_dns: true`, DNS answers pointing at metadata are rejected in both modes too; with `allow_private: true` the host is still resolved and the first answer pinned.
- DNS answers that include any private, reserved, or metadata address fail closed. A public answer is pinned (IPv4 preferred) and returned as HttpClient `resolve`.
- `dns_timeout` (default 2 seconds) stops the DNS child process when the deadline expires. The worker does not call `ini_set()`. The child is the PHP CLI found by `PhpExecutableFinder` (or `php_binary`); when none is usable, the lookup runs in-process and is bounded only by the system resolver timeouts.
- `allow_private_resolver` only replaces the private-network switch. Metadata checks run before it is consulted, so a resolver returning true never unblocks metadata. The resolver is read on every call: there is no cached value that outlives a request in worker mode.
- Defaults are deny-private and resolve-dns. Unknown config keys fail container compilation.

## What the caller must still do

- Pass `max_redirects: 0` (or re-check every redirect). The pin applies to the first host only.
- Do not enable `allow_private` for URLs chosen by untrusted users.
- Treat `allow_private: true` as an operator opt-in. The same applies to an `allow_private_resolver` service: back it with an admin-only setting, never with request input.
- With `resolve_dns: false` hostnames are not looked up, so a hostname whose DNS answer is a metadata or private address is **not** detected. Use it only for trusted names (Docker service names) and keep the HTTP client from reaching metadata at the network layer.
- Set HTTP connect/total timeouts and a response size limit on the HTTP client. This bundle does not open the connection.
- Keep PHP `max_execution_time` and the reverse-proxy write timeout strictly above `dns_timeout`.

## Secrets and cryptography

The bundle does not implement cryptography and does not embed secrets. Do not put tokens in committed config.

## Logging

The guard does not log the URL, the host, or DNS answers. Those values can contain credentials in the userinfo or query string. There is no `error_log` / `dump` on the decision path. Integrators should not add request logging of the raw URL at info level.

## Dependencies and updates

Runtime dependencies are Symfony components only. `composer audit --locked` runs in CI. Security updates are applied with Dependabot (Composer and GitHub Actions groups) and shipped as patch or minor releases.

## Permissions and exposure

No endpoints are registered. The public services are `OutboundUrlGuard`, `OutboundUrlGuardFactory` and `RuntimeOutboundUrlGuard`. Access control is the host application's.

## Reporting

Report vulnerabilities to **hectorfranco@nowo.tech**. Do not open a public issue for an unfixed bypass. See also [.github/SECURITY.md](../.github/SECURITY.md).

## Release security checklist (12.4.1)

Confirm before each tag:

| Item | Status |
| --- | --- |
| `docs/SECURITY.md` and `.github/SECURITY.md` exist and stay in English | Required |
| `.env` is gitignored; no secrets in the tree | Required |
| Flex recipe defaults are safe (`allow_private: false`) | Required |
| URL input is validated; no HTML output to escape | Required |
| `composer audit` reviewed in CI | Required |
| No secret logging | Required |
| No custom cryptography | N/A |
| No public endpoints | N/A |
| `dns_timeout` limits resolver wait (DoS) | Required |
| FrankenPHP worker (`reset_kernel: false`) audit | Pass, 2026-09-24. See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md). |
| AI security audit (REQ-SEC-004) | Pass (good), 2026-09-18. Overall risk Low. No open Critical or High findings. |

## AI security audit

Static review of `src/`, the Flex recipe, and these security docs on **2026-09-18**.

- **Grade:** Pass (good)
- **Risk:** Low
- **Method:** Cursor agent static pass (full package)
- **Residuals:** none in package code. Callers must still set `max_redirects` to 0 and use the DNS pin. That duty is documented above; it is not an open finding in this package.

The monorepo report `BUNDLES_SECURITY_ANALYSIS.md` records the same grade.
