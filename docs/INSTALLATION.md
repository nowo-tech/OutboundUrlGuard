# Installation

## Requirements

- PHP `>=8.2 <8.6`
- Symfony `^7.4 || ^8.0` (`config`, `dependency-injection`, `http-kernel`, `process`, `yaml`)

## Composer

```bash
composer require nowo-tech/outbound-url-guard-bundle
```

Symfony Flex registers `Nowo\OutboundUrlGuardBundle\NowoOutboundUrlGuardBundle` for all environments when the package type is `symfony-bundle`.

## Flex recipe

The recipe lives at `.symfony/recipe/nowo-tech/outbound-url-guard-bundle/1.0/`.

On install, Flex copies `config/packages/nowo_outbound_url_guard.yaml` with the safe defaults (`allow_private: false`, `resolve_dns: true`, `dns_timeout: 2`).

Without Flex, add the bundle to `config/bundles.php`:

```php
use Nowo\OutboundUrlGuardBundle\NowoOutboundUrlGuardBundle;

return [
    NowoOutboundUrlGuardBundle::class => ['all' => true],
];
```

Then create `config/packages/nowo_outbound_url_guard.yaml` as in [Configuration](CONFIGURATION.md).
