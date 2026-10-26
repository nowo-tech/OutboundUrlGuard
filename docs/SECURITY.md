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

- The URL string passed to `OutboundUrlGuard` (often a webhook or callback chosen by a user), in particular its host: trailing dots, non-ASCII (IDNA) spellings, percent signs, IPv6 zone IDs, and IPv6 forms that embed an IPv4 address.
- Bundle config: `allow_private`, `allow_private_resolver`, `resolve_dns`, `dns_timeout`, `php_binary`.
- The host `AllowPrivateResolverInterface` service, when configured (its answer switches private targets on for `RuntimeOutboundUrlGuard` / `OutboundUrlGuardFactory`).
- DNS answers for hostnames when `resolve_dns` is true.
- The DNS child process: up to three short-lived PHP CLI processes per check (A, AAAA, and the `gethostbynamel` fallback), each started with the hostname as an argument after `--`.
- No HTTP routes, forms, CLI commands, or admin UI.

## Threat model

| Threat | Risk |
| --- | --- |
| SSRF to loopback, RFC1918, or link-local | High if a user-controlled URL is fetched |
| Cloud metadata (`169.254.169.254`, Alibaba `100.100.100.200`, AWS IPv6 `fd00:ec2::254`, `fe80::/10`) including decimal, octal, hex, shorthand (`169.254.43518`), IPv4-mapped, and NAT64 forms | High |
| DNS rebinding after a check | High if the TCP connection is not pinned |
| Pin bypass through a host spelling the client keeps but the pin does not cover (`evil.test.`) | High (fixed in 2026-10-26 audit, M1) |
| Disguised literals the client normalises (fullwidth digits, IPv6 zone IDs, percent-encoding) | Medium with `resolve_dns: false` |
| Option injection into the DNS child (`-h` as a hostname) | Low: escaped the `dns_timeout` bound |
| Resource use: PHP child processes per check | Medium if URL submissions are not rate-limited |
| Redirect to a second host | High if `max_redirects` is left at the client default |
| Resolver hang | Medium under FrankenPHP / FPM (one stuck DNS call pins a worker) |
| XSS, CSRF, SQL injection, path traversal, deserialization | Not applicable: no HTML, no database, no file paths from the URL |

## Mitigations

- Schemes other than `http` and `https` are invalid.
- Private, reserved, and blocked hostnames are rejected unless `allow_private` is true. "Private" means every address that is not globally reachable (`FILTER_FLAG_GLOBAL_RANGE`, RFC 6890: CGNAT `100.64.0.0/10`, benchmarking, documentation, `2001::/23`, …) plus multicast (`224.0.0.0/4`, `ff00::/8`), site-local `fec0::/10`, and local-use NAT64 `64:ff9b:1::/48`.
- IPv4 literals are normalized the way curl and `inet_aton()` read them (`127.1`, `0177.0.0.1`, `0x7f.0.0.1`, `2130706433`) before they are classified. The IPv4 embedded in IPv4-mapped (`::ffff:0:0/96`), NAT64 (`64:ff9b::/96`) and 6to4 (`2002::/16`) IPv6 is classified as that IPv4, for literals and DNS answers alike.
- The host is classified the way Symfony HttpClient will send it: non-ASCII hosts are mapped with IDNA UTS46 (same flags as HttpClient), so `１２７.０.０.１` is `127.0.0.1`. Without ext-intl or `symfony/polyfill-intl-idn`, non-ASCII hosts are rejected (HttpClient refuses them too). Hosts containing `%` (percent-encoding, IPv6 zone IDs such as `[fe80::1%25eth0]`), spaces, or control characters are rejected.
- Hostnames must be valid syntax before any lookup: letters, digits, hyphens, underscores; labels of 1-63 characters that do not start or end with `-`; at most 253 characters; one optional trailing dot.
- The DNS pin is emitted for both `host` and `host.`, because HttpClient keeps a trailing dot in the request host and matches `resolve` keys exactly.
- Cloud metadata literals and names stay blocked in both modes. With `resolve_dns: true`, DNS answers pointing at metadata are rejected in both modes too; with `allow_private: true` the host is still resolved and the first answer pinned.
- DNS answers that include any private, reserved, or metadata address fail closed. A public answer is pinned (IPv4 preferred) and returned as HttpClient `resolve`.
- `dns_timeout` (default 2 seconds) stops the DNS child process when the deadline expires. The worker does not call `ini_set()`. The child is the PHP CLI found by `PhpExecutableFinder` (or `php_binary`) and receives the hostname after `--`, so it is never parsed as a CLI option; when none is usable, the lookup runs in-process and is bounded only by the system resolver timeouts.
- `allow_private_resolver` only replaces the private-network switch. Metadata checks run before it is consulted, so a resolver returning true never unblocks metadata. The resolver is read on every call: there is no cached value that outlives a request in worker mode.
- Defaults are deny-private and resolve-dns. Unknown config keys fail container compilation.

## What the caller must still do

- Pass `max_redirects: 0` (or re-check every redirect). The pin applies to the first host only.
- Apply the pin: pass `httpClientOptions()` to Symfony HttpClient. If you read `pinHost` / `pinIp` to configure another client (Guzzle, raw curl, a proxy), pin every spelling of the host that client will use (at least `host` and `host.`). Other clients may parse URLs differently from PHP `parse_url()` and Symfony HttpClient (IDNA, backslashes, userinfo); hand them the exact URL string the guard accepted, never a re-built one.
- Rate-limit URL submissions. With `resolve_dns: true`, each check can start up to three PHP CLI child processes (A, AAAA, and the `gethostbynamel` fallback), each bounded by `dns_timeout`.
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
| AI security audit (REQ-SEC-004) | Pass, 2026-10-26 re-audit. Overall risk Low after fixes. One Medium (M1) and four Low findings fixed in the next release; no open findings. |

## AI security audit

### 2026-10-26 re-audit

Static re-audit of `src/` on **2026-10-26** (v1.2.1, `3d6d2cb`). All findings were verified against the code and fixed in the next release, each with a regression test.

| ID | Severity | Finding | Fix |
| --- | --- | --- | --- |
| M1 | Medium | The trailing dot was stripped before pinning, but HttpClient keeps it in the request host and matches `resolve` keys exactly: `http://rebind.attacker.tld./` missed the pin and was re-resolved (DNS rebinding) on the default config. | `httpClientOptions()` pins `host` and `host.`; hosts with more than one trailing dot are rejected. |
| L1 | Low | CGNAT `100.64.0.0/10`, multicast, benchmarking, documentation and other RFC 6890 non-global ranges were allowed with `allow_private: false`; an AAAA answer such as `::ffff:127.0.0.1` passed as well. | `FILTER_FLAG_GLOBAL_RANGE` plus explicit multicast / site-local / local-use NAT64 checks, on the canonical (embedded IPv4) address. |
| L2 | Low | The DNS child ran `php -r CODE <host> <type>` without `--`: a host starting with `-` was parsed as a PHP CLI option, and a failing child fell back to the in-process lookup that `dns_timeout` does not bound. | `--` before the arguments; invalid hostname syntax is rejected before any lookup. |
| L3 | Low | AWS IPv6 metadata `fd00:ec2::254` was not treated as metadata, so it passed with `allow_private: true` or a resolver returning true. | Added to the metadata set (blocked in every mode); NAT64 `64:ff9b::/96` and 6to4 are unwrapped to the embedded IPv4. |
| L4 | Low | With `resolve_dns: false`, `http://１２７.０.０.１/` (UTS46 maps it to `127.0.0.1`) and `http://[fe80::1%25eth0]/` were accepted as hostnames. | Hosts are IDNA/UTS46-mapped before classification (rejected when no IDNA implementation is available); `%` is rejected. |

Residuals (caller duties, unchanged): set `max_redirects: 0` and apply the pin; rate-limit URL submissions (up to three PHP child processes per check); parser differences with non-Symfony clients (see [What the caller must still do](#what-the-caller-must-still-do)).

### 2026-09-18 audit

Static review of `src/`, the Flex recipe, and these security docs on **2026-09-18**.

- **Grade:** Pass (good)
- **Risk:** Low
- **Method:** Cursor agent static pass (full package)
- **Residuals:** none in package code. Callers must still set `max_redirects` to 0 and use the DNS pin. That duty is documented above; it is not an open finding in this package.

The monorepo report `BUNDLES_SECURITY_ANALYSIS.md` records the same grade.
