# Usage

Register the bundle (Flex does this from `type: symfony-bundle`). Inject `Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard`.

## Webhooks and other public callbacks

```php
$options = $guard->httpClientOptions($storedUrl);
$options['max_redirects'] = 0;
$response = $client->request('POST', $storedUrl, $options);
```

`httpClientOptions()` throws `UnsafeOutboundUrlException` (an `InvalidArgumentException`) when the URL is not `http`/`https`, the host is blocked, or DNS does not return a public address. The `resolve` key pins the host to that address.

Set `max_redirects` to `0`. A redirect is a second URL the pin does not cover.

DNS lookups use `dns_timeout` (default 2 seconds). See [Configuration](CONFIGURATION.md).

## Hubs on the private network

```php
if (!$guard->inspect($hubUrl, resolveDns: false)->isValid()) {
    // reject the stored hub URL
}
```

`resolveDns: false` still rejects private IP literals and metadata. It does not look up `mercure` or `php`.

## What stays in the application

The bundle does not read your settings store. Pass `allowPrivate: true` only when an operator has opted in. It does not send the request.
