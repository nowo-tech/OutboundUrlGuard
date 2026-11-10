# Usage

Register the bundle (Flex does this from `type: symfony-bundle`). Inject `Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard` (static `allow_private`) or `RuntimeOutboundUrlGuard` (`allow_private` read from a resolver on every call).

## Table of contents

- [Webhooks and other public callbacks](#webhooks-and-other-public-callbacks)
- [Hubs on the private network](#hubs-on-the-private-network)
- [Runtime private-URL switch](#runtime-private-url-switch)
- [What stays in the application](#what-stays-in-the-application)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)

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

## Using it from a DDD / hexagonal application

The bundle is an **infrastructure** policy: it has no entities, no Doctrine mapping, no events and no host types in
its API. Keep it out of the domain and call it from the adapter that opens the socket.

- Define your own port in the domain or application layer (for example `OutboundUrlPolicy` or
  `WebhookUrlValidator`) and implement it with `OutboundUrlGuardInterface` (since 1.5). The container alias points at
  `RuntimeOutboundUrlGuard`, which reads `allow_private` through `allow_private_resolver` (the static `allow_private`
  value when none is set), so it answers like `OutboundUrlGuard` with the same configuration. Alias the interface to
  your own service to replace the policy. `OutboundUrlGuard` itself does not implement the interface: its second
  argument is `$allowPrivate`, not `$resolveDns`.
- Map `OutboundUrlDecision` (`isValid()`, `result`, `message`) to your own result type; do not let it cross into the
  domain. `UnsafeOutboundUrlException` is an `InvalidArgumentException`.
- An operator "allow private URLs" setting stays in your settings store: expose it through
  `AllowPrivateResolverInterface` and set `allow_private_resolver`.
- Fixed DNS answers for tests: implement `HostnameDnsLookupInterface` and set `dns_lookup`, or build
  `new OutboundUrlGuard(dns: $fakeLookup)` directly. Without the container, the interface is
  `new RuntimeOutboundUrlGuard(new OutboundUrlGuardFactory(new ConfiguredAllowPrivateResolver(false), dns: $fakeLookup))`.

```php
namespace App\Infrastructure\Http;

use App\Application\Webhook\WebhookUrlPolicy;           // your port
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuardInterface;

final class GuardedWebhookUrlPolicy implements WebhookUrlPolicy
{
    public function __construct(private OutboundUrlGuardInterface $guard) {}

    public function rejectionReason(string $url): ?string
    {
        $decision = $this->guard->inspect($url);

        return $decision->isValid() ? null : $decision->message;
    }

    /** @return array<string, mixed> HttpClient options for the adapter that sends the request */
    public function requestOptions(string $url): array
    {
        return $this->guard->httpClientOptions($url) + ['max_redirects' => 0];
    }
}
```

Known limitations:

- `OutboundUrlDecision::$result` is a string (`valid`, `invalid`, `unsafe`); compare with the `RESULT_*` constants.
- No event is dispatched when a URL is rejected; log it in your adapter if you need an audit trail.
