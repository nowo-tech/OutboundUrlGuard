# Configuration

```yaml
nowo_outbound_url_guard:
    allow_private: false
    resolve_dns: true
    dns_timeout: 2
```

| Key | Default | Meaning |
| --- | --- | --- |
| `allow_private` | `false` | Permit loopback, RFC1918, and names such as `localhost`. Cloud metadata stays blocked. DNS is not consulted, because the caller already accepted private answers. |
| `resolve_dns` | `true` | Resolve hostnames and pin the first public address. Set `false` for Docker service names (`mercure`, `php`) that must stay valid without a public DNS record. |
| `dns_timeout` | `2` | Seconds allowed for one DNS lookup. The lookup runs in a child PHP process that is stopped when the deadline expires. Minimum `0.1`. |

Each call can override the boolean defaults:

```php
$guard->inspect($url, allowPrivate: true, resolveDns: false);
```

## Timeout hierarchy (FrankenPHP / FPM)

DNS is the only blocking call in this bundle. There is no subprocess and no HTTP client.

| Layer | This package |
| --- | --- |
| Operation | `dns_timeout` (default 2 seconds) on the DNS child process |
| PHP `max_execution_time` | Must be **greater** than `dns_timeout` |
| Reverse proxy / Caddy write timeout | Must be **greater** than PHP, in the host application |

This repository does not ship a FrankenPHP demo. Host applications that run the guard inside a worker should keep `dns_timeout` below PHP and the proxy write timeout. Raise those outer deadlines in the same change if you raise `dns_timeout`.

A lookup that returns no address is rejected (`Outbound URL host could not be resolved`). The bundle does not retry.
