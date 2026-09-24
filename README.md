# Outbound URL Guard

[![CI](https://github.com/nowo-tech/OutboundUrlGuard/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/OutboundUrlGuard/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/outbound-url-guard-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/outbound-url-guard-bundle)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/outbound-url-guard-bundle.svg)](https://packagist.org/packages/nowo-tech/outbound-url-guard-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com)
[![GitHub stars](https://img.shields.io/github/stars/nowo-tech/outbound-url-guard-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/OutboundUrlGuard)
[![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** Install it from [Packagist](https://packagist.org/packages/nowo-tech/outbound-url-guard-bundle) and star [OutboundUrlGuard](https://github.com/nowo-tech/OutboundUrlGuard).

Symfony bundle that checks an outbound `http` or `https` URL before your app opens the connection. It blocks loopback, private, and reserved addresses, plus cloud metadata, and can pin a hostname to the first public DNS answer.

> Compatible with Symfony 7.4, 8.0, and 8.1. PHP 8.2+ (Symfony 8.x requires PHP 8.4+).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**, including when `reset_kernel` is false (no per-request state; DNS bounded by `dns_timeout`). See [FrankenPHP worker audit](docs/FRANKENPHP-WORKER-AUDIT.md).

## Features

- Rejects schemes other than `http` and `https`.
- Blocks loopback, RFC1918, link-local, unique-local, and other addresses PHP marks private or reserved.
- Blocks cloud metadata even when `allow_private` is true, including decimal, hex, and IPv4-mapped forms.
- Optional DNS resolution with an HttpClient `resolve` pin (IPv4 preferred) and a configurable socket timeout.
- Does not open the HTTP connection. Callers still set TLS, timeouts, and `max_redirects: 0`.

## Installation

```bash
composer require nowo-tech/outbound-url-guard-bundle
```

Flex registers the bundle from `type: symfony-bundle`. See [Installation](docs/INSTALLATION.md).

## Requirements

- PHP `>=8.2 <8.6`
- Symfony components `^7.4 || ^8.0` (`symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/process`, `symfony/yaml`)

## Configuration

```yaml
nowo_outbound_url_guard:
    allow_private: false
    resolve_dns: true
    dns_timeout: 2.0
```

Full key list: [Configuration](docs/CONFIGURATION.md).

## Usage

```php
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;

$options = $guard->httpClientOptions($url);
$options['max_redirects'] = 0;
```

Docker service names: `$guard->assertSafe('http://mercure/.well-known/mercure', resolveDns: false);`

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [FrankenPHP worker audit](docs/FRANKENPHP-WORKER-AUDIT.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [PSR evaluation](docs/PSR.md)
- [GitHub CI](docs/GITHUB_CI.md)

## Tests and coverage

- PHPUnit unit and integration tests (`composer test`, `make test`)
- PHP: 100%
- TS/JS: N/A
- Python: N/A

`make test-coverage` prints the PHP Lines percentage and fails when Clover statement coverage is below 99%. `make test-coverage-100` requires 100%.

## License

MIT. See [LICENSE](LICENSE).

## Contributing

See [Contributing](docs/CONTRIBUTING.md) and the [Code of Conduct](CODE_OF_CONDUCT.md).

## Version policy

The `1.x` line is supported. Report vulnerabilities privately as described in [Security](docs/SECURITY.md).

## Author

[Héctor Franco Aceituno](https://github.com/HecFranco) and [Nowo.tech](https://nowo.tech).
