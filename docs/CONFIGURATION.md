# Configuration

```yaml
nowo_outbound_url_guard:
    allow_private: false
    allow_private_resolver: ~
    resolve_dns: true
    dns_timeout: 2
    php_binary: ~
```

| Key | Default | Meaning |
| --- | --- | --- |
| `allow_private` | `false` | Permit loopback, RFC1918, and names such as `localhost`. Cloud metadata stays blocked: literal addresses always, and DNS answers when `resolve_dns` is true (the hostname is still resolved and the first answer is pinned). |
| `allow_private_resolver` | `null` | Service id (optional leading `@`) of an `AllowPrivateResolverInterface`. `OutboundUrlGuardFactory` and `RuntimeOutboundUrlGuard` call its `allowPrivate()` on every check. `null` uses the static `allow_private` value. `OutboundUrlGuard` ignores it. Cloud metadata stays blocked whatever it returns. |
| `resolve_dns` | `true` | Resolve hostnames and pin the first public address. Set `false` for Docker service names (`mercure`, `php`) that must stay valid without a public DNS record. With `false`, hostnames are not looked up, so a name whose DNS points at a metadata or private address is **not** detected; only literal addresses and known metadata names are checked. |
| `dns_timeout` | `2` | Seconds allowed for one DNS lookup. The lookup runs in a child PHP CLI process that is stopped when the deadline expires. Minimum `0.1`. |
| `php_binary` | `null` | PHP CLI used for the DNS child process. `null` auto-detects it with Symfony `PhpExecutableFinder` (`PHP_BINARY` is the server binary under php-fpm or FrankenPHP, not the CLI). `''` disables the child process. When no CLI is found, or the binary cannot run the lookup, DNS runs in-process: it works, but is only bounded by the system resolver timeouts (`/etc/resolv.conf`), not by `dns_timeout`. |

Each call can override the boolean defaults:

```php
$guard->inspect($url, allowPrivate: true, resolveDns: false);
```

## Runtime private-URL switch

When "allow private URLs" is an operator setting, implement the resolver on the service that reads it:

```php
use Nowo\OutboundUrlGuardBundle\Guard\AllowPrivateResolverInterface;

final class OpsSettings implements AllowPrivateResolverInterface
{
    public function allowPrivate(): bool
    {
        return $this->allowPrivateUrls(); // read per call, no cross-request cache
    }
}
```

```yaml
nowo_outbound_url_guard:
    allow_private_resolver: App\Settings\OpsSettings
```

Then inject `RuntimeOutboundUrlGuard` (or `OutboundUrlGuardFactory`). See [Usage](USAGE.md#runtime-private-url-switch).

## Timeout hierarchy (FrankenPHP / FPM)

DNS is the only blocking call in this bundle. It runs in a short-lived PHP CLI child process (see `php_binary`; without a CLI it runs in-process and falls back to the resolver's own timeouts). This bundle does not open an HTTP connection.

| Layer | This package |
| --- | --- |
| Operation | `dns_timeout` (default 2 seconds) on the DNS child process |
| PHP `max_execution_time` | Must be **greater** than `dns_timeout` |
| Reverse proxy / Caddy write timeout | Must be **greater** than PHP, in the host application |

This repository does not ship a FrankenPHP demo. Host applications that run the guard inside a worker should keep `dns_timeout` below PHP and the proxy write timeout. Raise those outer deadlines in the same change if you raise `dns_timeout`.

With FrankenPHP worker mode and `reset_kernel: false`, no bundle-specific reset is required: services are immutable and DNS pins are not cached. Details: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

A lookup that returns no address is rejected (`Outbound URL host could not be resolved`). The bundle does not retry.
