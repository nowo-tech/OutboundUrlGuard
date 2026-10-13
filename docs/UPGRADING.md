# Upgrading

## Unreleased

## To 1.1.0

From **1.0.4** — DNS lookups work under FPM/FrankenPHP; metadata answers blocked with allow_private; inet_aton IPv4 forms normalized.

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

- DNS lookups no longer depend on `PHP_BINARY` (which is not the CLI under FPM/FrankenPHP); new `php_binary` option (`null` = auto-detect, `''` = always in-process).
- With `allow_private: true` and `resolve_dns: true`, hostnames are now resolved and pinned: unresolvable names (previously accepted) and names resolving to cloud metadata are rejected. Use `resolve_dns: false` for the old no-lookup behaviour (which cannot detect metadata answers).
- Shorthand/octal/hex/decimal IPv4 literals (`127.1`, `0177.0.0.1`, `0x7f.0.0.1`, `2130706433`) are now classified as private/metadata instead of passing.

## To 1.0.4

From **1.0.3** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.0.3

From **1.0.2** — dependency refresh.

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.0.2

From **1.0.1** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/outbound-url-guard-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.0.0 to 1.0.1

No breaking API or config changes. Defaults stay the same:

| Key | Default |
| --- | --- |
| `allow_private` | `false` |
| `resolve_dns` | `true` |
| `dns_timeout` | `2` |

What changed for integrators:

- Documented **100% FrankenPHP worker compatibility** when `reset_kernel` is false (no bundle reset hook required). See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
- Dev dependency / CI: PHPStan enables FrankenPHP **worker-strict** and **hardening** rulesets. Application code is unchanged.

```bash
composer update nowo-tech/outbound-url-guard-bundle
```

## From nothing to 1.0.0

First release. There is no previous Packagist version to migrate from.

```bash
composer require nowo-tech/outbound-url-guard-bundle
```

With Symfony Flex, the recipe registers the bundle and copies `config/packages/nowo_outbound_url_guard.yaml`.

Defaults:

| Key | Default |
| --- | --- |
| `allow_private` | `false` |
| `resolve_dns` | `true` |
| `dns_timeout` | `2` |

After `httpClientOptions()` accepts a URL, set `max_redirects` to `0` on the HTTP client. The pin covers the first host only. See [Usage](USAGE.md).

No public config key has been removed. Future removals will keep a deprecated alias for at least one minor while the package is pre-2.0, and will be listed here.
