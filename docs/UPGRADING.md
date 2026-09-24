# Upgrading

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
