# Spec-driven development

## Table of contents

- [Purpose](#purpose)
- [Product layers](#product-layers)
- [User stories](#user-stories)
- [Bundle functional scope](#bundle-functional-scope)
- [Public API (Packagist contract)](#public-api-packagist-contract)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers (Makefile)](#requirement-identifiers-makefile)
- [Contributor workflow](#contributor-workflow)
- [Relationship with Engram](#relationship-with-engram)
- [GitHub Spec Kit](#github-spec-kit)
  - [Spec Kit workflow (summary)](#spec-kit-workflow-summary)
- [See also](#see-also)

## Purpose

This document describes **what Outbound URL Guard guarantees**, how behavior is proven, and how Spec Kit / Engram fit into the maintainer workflow.

## Product layers

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) and operator manual [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Integrator contract** — `OutboundUrlGuard`, config alias `nowo_outbound_url_guard`, DNS pin options.
3. **Repo `REQ-*` traceability** — Makefile and CI. This package has no demos.

Mechanical proof is **PHPUnit** + **PHPStan**. There is no separate executable spec language.

## User stories

| ID | Intent | Scope / docs |
| -- | ------ | ------------ |
| US-01 | Reject private and metadata URLs before a webhook is sent | `OutboundUrlGuard` · [USAGE](USAGE.md) |
| US-02 | Allow Docker service names without public DNS | `resolve_dns: false` · [CONFIGURATION](CONFIGURATION.md) |
| US-03 | Pin a public hostname so a later DNS answer cannot rebind | `httpClientOptions()` · [USAGE](USAGE.md) |
| US-04 | Bound DNS wait time on FrankenPHP / FPM | `dns_timeout` · [CONFIGURATION](CONFIGURATION.md) |

## Bundle functional scope

**Goal:** One policy for outbound `http`/`https` URLs so callers do not open SSRF targets.

**In scope:** scheme check, private/reserved addresses, blocked hostnames, cloud metadata (including obfuscated literals), optional DNS pin, configurable DNS socket timeout, Symfony config defaults.

**Explicit non-goals:** sending HTTP, TLS policy, redirect following, response size limits, webhook signature verification, an admin UI.

**Not part of the Packagist API:** repository tooling (`Makefile`, `.github/`, `.cursor/`).

## Public API (Packagist contract)

| Artifact | Responsibility |
| --- | --- |
| `OutboundUrlGuard::inspect()` / `assertSafe()` / `httpClientOptions()` | Classify a URL and optionally return an HttpClient `resolve` pin |
| `OutboundUrlDecision` / `OutboundUrlResult` | Valid, invalid, or unsafe outcome |
| `UnsafeOutboundUrlException` | Thrown by `assertSafe()` and `httpClientOptions()` |
| Config alias `nowo_outbound_url_guard` | `allow_private`, `resolve_dns`, `dns_timeout` |

## Validating the functional spec

| Command | Proves |
| ------- | ------ |
| `make test` / `make test-coverage` | Unit + integration behavior (PHP statements >= 99%, target 100%) |
| `make phpstan` / `make cs-check` / `make rector-dry` | Static quality |
| `make release-check` | Full pre-release gate, including open-PR and co-author checks |

Behavior changes **require** tests under `tests/`.

## Requirement identifiers (Makefile)

| ID | Location | Meaning |
| -- | -------- | ------- |
| REQ-MAKE-001 | root `Makefile` | `ensure-up`, standard targets, `release-check` |
| REQ-MAKE-002 | root `Makefile` `release-check` | Co-author check, open PRs, style, Rector, PHPStan, coverage |
| REQ-MAKE-006 | `setup-hooks` | Install `.githooks/commit-msg` |
| REQ-MAKE-008 | `update-deps` | `composer update` in the bundle container (no demos) |
| REQ-MAKE-009 | `Makefile` | No path outside this git repository |
| REQ-MAKE-010 | `COMPOSE_BIN` | Docker Compose V2 with V1 fallback |
| REQ-GIT-001 | `.githooks/`, `.scripts/check-no-cursor-coauthor.sh` | No Cursor co-author trailers |
| REQ-REL-003 | `.scripts/check-open-prs.sh` | No unresolved open pull requests |
| REQ-RUNTIME-001 | `dns_timeout`, `HostnameDnsLookup` | Explicit DNS child-process timeout |
| REQ-TEST-003 | `.scripts/coverage-check.sh` | Statement coverage gate |

When scripted behavior changes, update or add the matching `REQ-*` comment.

## Contributor workflow

1. Clarify intent against baseline `FR-*` / user stories.
2. Implement with tests under `tests/Unit` and `tests/Integration`.
3. Keep `specs/001-baseline/code-inventory.md` at 100% of `src/`.
4. Update integrator docs when behavior or config changes.
5. Run `make release-check` before tagging.

## Relationship with Engram

[`ENGRAM.md`](ENGRAM.md) covers Cursor MCP. This file owns **product behavior + local REQ-* traceability**. Engram does not replace it.

## GitHub Spec Kit

Full operator manual: [`SPEC-KIT.md`](SPEC-KIT.md). Baseline: [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) + [`code-inventory.md`](../specs/001-baseline/code-inventory.md).

### Spec Kit workflow (summary)

1. Specify / clarify in `specs/`
2. Plan and tasks via Spec Kit skills
3. Implement against `FR-*`
4. Keep inventory at 100% of `src/`
5. Run `make release-check` before tagging

## See also

- [SPEC-KIT.md](SPEC-KIT.md)
- [USAGE.md](USAGE.md)
- [CONFIGURATION.md](CONFIGURATION.md)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [RELEASE.md](RELEASE.md)
- [SECURITY.md](SECURITY.md)
