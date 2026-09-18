# Security

## Scope

This bundle decides whether an outbound `http` or `https` URL is safe to open. It does not send the request, terminate TLS, follow redirects, or store credentials.

## Attack surface

- The URL string passed to `OutboundUrlGuard` (often a webhook or callback chosen by a user).
- Bundle config: `allow_private`, `resolve_dns`, `dns_timeout`.
- DNS answers for hostnames when `resolve_dns` is true.
- No HTTP routes, forms, CLI commands, or admin UI.

## Threat model

| Threat | Risk |
| --- | --- |
| SSRF to loopback, RFC1918, or link-local | High if a user-controlled URL is fetched |
| Cloud metadata (`169.254.169.254`, Alibaba `100.100.100.200`, `fe80::/10`) including decimal, hex, and IPv4-mapped forms | High |
| DNS rebinding after a check | High if the TCP connection is not pinned |
| Redirect to a second host | High if `max_redirects` is left at the client default |
| Resolver hang | Medium under FrankenPHP / FPM (one stuck DNS call pins a worker) |
| XSS, CSRF, SQL injection, path traversal, deserialization | Not applicable: no HTML, no database, no file paths from the URL |

## Mitigations

- Schemes other than `http` and `https` are invalid.
- Private, reserved, and blocked hostnames are rejected unless `allow_private` is true.
- Cloud metadata stays blocked in both modes.
- DNS answers that include any private, reserved, or metadata address fail closed. A public answer is pinned (IPv4 preferred) and returned as HttpClient `resolve`.
- `dns_timeout` (default 2 seconds) stops the DNS child process when the deadline expires. The worker does not call `ini_set()`.
- Defaults are deny-private and resolve-dns. Unknown config keys fail container compilation.

## What the caller must still do

- Pass `max_redirects: 0` (or re-check every redirect). The pin applies to the first host only.
- Do not enable `allow_private` for URLs chosen by untrusted users.
- Treat `allow_private: true` as an operator opt-in. It skips DNS, so a public hostname is not pinned in that mode.
- Set HTTP connect/total timeouts and a response size limit on the HTTP client. This bundle does not open the connection.
- Keep PHP `max_execution_time` and the reverse-proxy write timeout strictly above `dns_timeout`.

## Secrets and cryptography

The bundle does not implement cryptography and does not embed secrets. Do not put tokens in committed config.

## Logging

The guard does not log the URL, the host, or DNS answers. Those values can contain credentials in the userinfo or query string. There is no `error_log` / `dump` on the decision path. Integrators should not add request logging of the raw URL at info level.

## Dependencies and updates

Runtime dependencies are Symfony components only. `composer audit --locked` runs in CI. Security updates are applied with Dependabot (Composer and GitHub Actions groups) and shipped as patch or minor releases.

## Permissions and exposure

No endpoints are registered. The public service is `OutboundUrlGuard`. Access control is the host application's.

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
| AI security audit (REQ-SEC-004) | Pass (good), 2026-09-18. Overall risk Low. No open Critical or High findings. |

## AI security audit

Static review of `src/`, the Flex recipe, and these security docs on **2026-09-18**.

- **Grade:** Pass (good)
- **Risk:** Low
- **Method:** Cursor agent static pass (full package)
- **Residuals:** none in package code. Callers must still set `max_redirects` to 0 and use the DNS pin. That duty is documented above; it is not an open finding in this package.

The monorepo report `BUNDLES_SECURITY_ANALYSIS.md` records the same grade.
