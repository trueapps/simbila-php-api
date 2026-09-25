# Simbila.com PHP API client

A framework-agnostic PHP client for Simbila's `/api/v1` (Bearer API key). No
Yii dependency, no caching, no config resolution — an application wraps this
for that (see [Using it from Yii](#using-it-from-yii) below).

Replaces the old `/api` client (username/password + `appId`/`appUser` headers)
that used to live in this package as `Simbila.php` / `YiiSimbila` — Simbila
retired that authentication in favour of a Bearer API key. If you still have
`protected/extensions/simbila/*` vendored into an app, delete it and use this
package instead.

## Install

```bash
composer require trueapps/simbila-php-api
```

## Usage

```php
use TrueApps\SimbilaApi\Client;

$client = new Client('sk_live_...', ['appId' => 'lunchdrive']);

// who does this key belong to?
$identity = $client->whoami();

// a reseller's subscription billing of its own "app users" (appId/appUser)
$client->billing()->status('345');
$client->billing()->create('345', ['fy_name' => '...', 'plan_text' => 'Plan', 'plan_price' => 99, 'plan_vat' => 21, ...]);

// a Simbila account's own invoicing of its own clients (no appId/appUser)
$invoice = $client->invoice()->create(['i_type' => 'invoice', 'i_firm_name' => '...', 'items' => [...]]);
$client->invoice()->send($invoice['i_id']);
$pdfBytes = $client->invoice()->pdf($invoice['i_id']);

// app-billing proformas (card or QR) against a reseller's own app users
$client->appBilling()->purchase('345', 'ext-ref-1', $items, saveCard: false, returnUrl: null, lang: 'cs');

// PayMe QR request status/cancel
$client->payMe()->status(5001);
```

`$options` accepted by the constructor:

| key | default | notes |
|---|---|---|
| `baseUrl` | `https://simbila.com` | a trailing `/api` or `/api/v1` is stripped, so the old `simbilaUrl` value works unchanged |
| `appId` | — | required only for `billing()`/`appBilling()` |
| `timeout` | `20.0` | seconds |
| `httpClient` | a default Guzzle client | inject a PSR-18/Guzzle client for tests or a shared connection pool |

Every call throws `TrueApps\SimbilaApi\Exception\SimbilaApiException` on a
non-2xx response or a transport failure (`getStatus()` is `0` for the latter);
see its `isNotFound()`/`isUnauthorized()`/`isTransient()`/`getRetryAfter()`
helpers and `extra()` for anything else Simbila put in the error body.

## Using it from Yii

This package does not know about Yii. Wrap `Client` in a thin adapter that
resolves the API key/appId from your app's config and, if you want it, adds
caching:

```php
final class SimbilaClientFactory
{
    public static function fromParams(): \TrueApps\SimbilaApi\Client
    {
        $apiKey = trim((string) Yii::app()->params['simbilaApiKey']);
        if ($apiKey === '') {
            throw new \TrueApps\SimbilaApi\Exception\SimbilaApiException(0, 'not_configured', 'Simbila is not configured');
        }
        return new \TrueApps\SimbilaApi\Client($apiKey, ['appId' => 'lunchdrive']);
    }
}
```

## Development

```bash
composer install
composer test
```
