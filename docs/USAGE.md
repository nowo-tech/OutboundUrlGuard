# Usage

Register the bundle (Flex does this from `type: symfony-bundle`). Inject `Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard` (static `allow_private`) or `RuntimeOutboundUrlGuard` (`allow_private` read from a resolver on every call).

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

`resolveDns: false` still rejects private IP literals and metadata literals or names. It does not look up `mercure` or `php`, so it cannot detect a hostname whose DNS answer is a private or metadata address.

## Runtime private-URL switch

Configure `allow_private_resolver` (see [Configuration](CONFIGURATION.md#runtime-private-url-switch)) and inject `Nowo\OutboundUrlGuardBundle\Guard\RuntimeOutboundUrlGuard`:

```php
$options = $runtimeGuard->httpClientOptions($storedUrl); // reads allowPrivate() now
$options['max_redirects'] = 0;

$runtimeGuard->assertSafe($hubUrl, resolveDns: false);
```

`RuntimeOutboundUrlGuard` has the same `inspect()`, `assertSafe()` and `httpClientOptions()` as `OutboundUrlGuard`, except `allowPrivate` comes from the resolver on every call (only `resolveDns` can be overridden). When you need the plain guard, call `OutboundUrlGuardFactory::create()` per check instead of keeping the returned instance. Cloud metadata stays blocked whatever the resolver returns.

## What stays in the application

The bundle does not read your settings store: expose it through `AllowPrivateResolverInterface`, or pass `allowPrivate: true` only when an operator has opted in. It does not send the request.
