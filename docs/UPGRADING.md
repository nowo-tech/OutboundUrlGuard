# Upgrading

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
