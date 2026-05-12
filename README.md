# MakeCommerce PHP Shipping SDK

**Package:** `maksekeskus/php-shipping-sdk` | **Version:** 1.5.6 | **PHP:** >= 7.4 | **License:** MIT

A PHP client library for integrating with MakeCommerce shipping services. Supports shipment creation, rate calculation, pickup point lookups, label retrieval, carrier credential validation, and the embedded shipping manager UI.

> **Before you can create shipments, fetch rates, print labels, you must complete the one-time shop setup described in [Required First-Time Setup](#required-first-time-setup) below.** Skipping it will cause API errors — the platform has no sender address or carrier configuration to work with yet.

---

## Table of Contents

1. [Installation](#installation)
2. [Credentials](#credentials)
3. [Client Initialization](#client-initialization)
4. [Required First-Time Setup](#required-first-time-setup)
5. [Method Reference](#method-reference)
6. [Response Object](#response-object-mcresponse)
7. [Error Handling](#error-handling)
8. [Complete Example](#complete-example)
9. [Development](#development)

---

## Installation

```bash
composer require maksekeskus/php-shipping-sdk
```

Requires the `ext-json` PHP extension (enabled by default in most environments).

---

## Credentials

Obtain your `shopId` and `secretKey` from the merchant portal:

| Environment | Portal URL |
|-------------|-----------|
| TEST | https://merchant.test.maksekeskus.ee |
| LIVE | https://merchant.maksekeskus.ee |

> **Important:** Credentials are environment-specific. TEST credentials will not work against the LIVE API and vice versa.

### Public Test Credentials

Use these shared credentials against `Environment::TEST` to try the SDK without registering:

| Key | Value |
|-----|-------|
| `shopId` | `3425d8b7-0225-4367-8c6f-16b1aba8d766` |
| `secretKey` | `J5S4lcVjC1QfJec8IQPhHSKeAiEf10bPV7KrHPx9AmIl9nCoEtNtJo63SF0YKpFQ` |
| Publishable key | `79p15UvwBLlZfqmoMY8D8LAjq4CwI8Tn` |

> These are intentionally public. Replace them with your own credentials before going to production.

---

## Client Initialization

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use MakeCommerceShipping\SDK\Environment;
use MakeCommerceShipping\SDK\Http\MakeCommerceClient;

$appInfo = [
    'module'           => 'MyShippingPlugin',
    'module_version'   => '1.0.0',
    'platform'         => 'WooCommerce', // Name of the platform you are using
    'platform_version' => '8.0.0',
];

$mcs = new MakeCommerceClient(
    Environment::TEST,    // Environment::TEST or Environment::LIVE
    'YOUR_SHOP_ID',       // From merchant portal
    'YOUR_SECRET_KEY',    // From merchant portal
    'your-persistent-uuid',// Generated once per installation; stored and reused on every request
    $appInfo
);
```

### Constructor Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$environment` | `string` | Yes | `Environment::TEST` or `Environment::LIVE` |
| `$shopId` | `string` | Yes | Shop identifier from merchant portal |
| `$shopSecret` | `string` | Yes | Secret key from merchant portal |
| `$instanceId` | `string` | Yes | Unique identifier for this specific shop installation. Must be a random UUID or unique string generated **once** per installation and stored persistently. Every request from the same shop installation must use the **same** value — a new installation (or a different e-commerce platform) must use a different value. Never regenerate it on each request. |
| `$appInfo` | `array` | Yes | Platform metadata — see keys below |

### `$appInfo` Keys

| Key | Type | Description |
|-----|------|-------------|
| `module` | `string` | Name of your module/plugin |
| `module_version` | `string` | Version of your module/plugin |
| `platform` | `string` | E-commerce platform name (e.g. `'WooCommerce'`) |
| `platform_version` | `string` | Platform version |

### `$instanceId` rules

> **Generate once, store, never change.**
> `$instanceId` must be a random UUID (or any sufficiently unique string) that you generate **once** when the plugin/module is first installed and then persist in your configuration storage. Every subsequent request from that same shop installation must send the exact same value.
>
> - **Same shop, same platform** → always the same `$instanceId`.
> - **New installation** (fresh install on a new server, staging clone, etc.) → generate a new `$instanceId`.
>
> If the value changes between requests the SDK will treat them as different integration instances.

### Optional: Locale

```php
$mcs->setLocale('et'); // Default is 'en'
```

Affects the language of the embedded shipping manager iframe.

---

## Required First-Time Setup

> **You cannot create shipments, fetch rates, print labels until this setup is complete.**
> The platform needs a sender address and carrier configuration, and those are entered by the merchant inside the embedded iframe. This is a one-time step per shop, but it must happen before any other SDK feature will work.

The setup consists of three steps that must be completed in order:

### Step 1 — Connect the shop

**Endpoint:** `POST https://shipping-manager.makecommerce.net/connect`

Call `connectShop()` to register the shop with the platform and receive a short-lived JWT.

```php
// PHP 8.0+ named arguments recommended so parameter order does not matter
$response = $mcs->connectShop(
    userAgent:   $_SERVER['HTTP_USER_AGENT'],
    remoteAddr:  $_SERVER['HTTP_HOST'],
    orderUrl:    'https://myshop.com/order/{id}/view', // optional
    webhookUrl:  'https://myshop.com/webhook',         // optional
);
```

**Request body:**

```json
{
  "shopId":          "YOUR_SHOP_ID",
  "secretKey":       "YOUR_SECRET_KEY",
  "instanceId":      "my-woocommerce-plugin",
  "webhookUrl":      "https://myshop.com/webhook",
  "orderUrl":        "https://myshop.com/order/{id}/view",
  "HTTP_USER_AGENT": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
  "REMOTE_ADDR":     "https://myshop.com"
}
```

**Response (`200 OK`):**

```json
{
  "jwt": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzaG9wSWQiOiJZT1VSX1NIT1BfSUQiLCJpbnN0YW5jZUlkIjoibXktd29vY29tbWVyY2UtcGx1Z2luIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"
}
```

Pass `$response->body->jwt` to `getIframeUrl()` in the next step.

> **Note:** `remoteAddr` must be an HTTPS URL in production. HTTP is only allowed for `localhost`.

### Step 2 — Embed the shipping manager iframe

Pass the JWT to `getIframeUrl()` and render the iframe on a settings or onboarding page in your plugin/module.

```php
$iframeUrl = $mcs->getIframeUrl($response->body->jwt);

echo '<iframe src="' . $iframeUrl . '" width="100%" height="720px"></iframe>';
```

### Step 3 — Merchant saves their sender details inside the iframe

The merchant must open the embedded UI and fill in their **sender address**. No shipments can be dispatched until this is done. There is nothing to call in the SDK for this step — the merchant completes it interactively in the browser.

**Only after the merchant saves their details in the iframe are the following features available:**

- `getRates()` — fetch shipping rates
- `listCarrierDestinations()` — list pickup points
- `createShipment()` / `updateShipment()` — create or update shipments
- `getLabel()` — retrieve shipping labels

---

## Method Reference

Every request is authenticated with HTTP Basic Auth (`shopId:secretKey`) and includes these headers:

| Header | Value |
|--------|-------|
| `Authorization` | `Basic base64(shopId:secretKey)` — handled by the SDK via Guzzle |
| `Accept` | `application/json` |
| `Content-Type` | `application/json` |
| `MakeCommerce-Shop` | Your shop ID |
| `MakeCommerce-Shop-Instance` | Your instance ID |
| `MakeCommerce-Shipping-AppInfo` | `base64(json_encode($appInfo))` |
| `MakeCommerce-User-Locale` | Locale string (default: `en`) |

For the full `connectShop()` walkthrough see [Required First-Time Setup](#required-first-time-setup).

### `getIframeUrl(string $jwt): string`

Builds the shipping manager iframe URL from the JWT returned by `connectShop()`.

```php
$iframeUrl = $mcs->getIframeUrl($response->body->jwt);

echo '<iframe src="' . $iframeUrl . '" width="100%" height="720px"></iframe>';
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$jwt` | `string` | JWT from `connectShop()` at `$response->body->jwt` |

**Returns:** `string` — Full URL for the iframe, e.g.:
```
https://shipping-manager.makecommerce.net/public/ui/?jwt=eyJ...&locale=en&platform=WooCommerce
```

The `locale` and `platform` query parameters are taken automatically from `setLocale()` and the `$appInfo` array passed at construction — there is no way to override them per call.

---

### Base URLs per environment

| | TEST | LIVE |
|-|------|------|
| `{shipping}` | `https://shipping.test.makecommerce.net` | `https://shipping.makecommerce.net` |
| `{manager}` | `https://shipping-manager.test.makecommerce.net` | `https://shipping-manager.makecommerce.net` |
| `{api}` | `https://api.test.maksekeskus.ee` | `https://api.maksekeskus.ee` |

### Quick reference

| Method | HTTP | Endpoint | Returns |
|--------|------|----------|---------|
| `connectShop()` | POST | `{manager}/connect` | `MCResponse` — JWT at `->body->jwt` |
| `getIframeUrl()` | — | builds `{manager}/public/ui/?jwt=...` | `string` URL |
| `getRates()` | POST | `{shipping}/rates` | decoded body object |
| `listCarrierDestinations()` | GET | `{shipping}/pickuppoint/{country}` | decoded body array |
| `createShipment()` | POST | `{shipping}/shipments` | decoded body — use `->trackingId` for `getLabel()` / `getShipment()` / `updateShipment()` |
| `getShipments()` | GET | `{shipping}/shipments?size&pageToken` | decoded body object |
| `getShipment()` | GET | `{shipping}/shipments/{trackingId}` | decoded body object |
| `updateShipment()` | PUT | `{shipping}/shipments/{trackingId}` | decoded body object |
| `getLabel()` | GET | `{shipping}/shipments/{trackingId}/label` | raw PDF string |
| `validateCarrierCredentials()` | GET | `{shipping}/authenticate` | `bool` |
| `changeSubscriptionPlan()` | POST | `{api}/v1/subscription/activate` | `bool` |
| `deactivateSubscriptionPlan()` | POST | `{api}/v1/subscription/deactivate` | `bool` |

---

### `getRates(array $data, array &$location = []): object`

**Endpoint:** `POST https://shipping.makecommerce.net/rates`

```php
$location = [];
$rates = $mcs->getRates(
    [
        'weight'      => 2500,  // grams — rounded to int automatically
        'destination' => 'EE',  // 2-letter ISO country code
    ],
    $location // filled with location data from MakeCommerce-Rates-Location header
);
```

**Request body:**

```json
{
  "weight": 2500,
  "destination": "EE"
}
```

**Response (`200 OK`):**

```json
[
  {
    "carrier": "omniva",
    "method": "pickuppoint",
    "price": 3.49,
    "currency": "EUR"
  },
  {
    "carrier": "dpd",
    "method": "courier",
    "price": 5.99,
    "currency": "EUR"
  }
]
```

**Returns:** `object` — decoded response body.

---

### `listCarrierDestinations(string $carrier, string $country): array`

**Endpoint:** `GET https://shipping.makecommerce.net/pickuppoint/{country}`

Additional header: `MakeCommerce-Carrier: {carrier}`. Country code is lowercased automatically. No request body.

```php
$pickupPoints = $mcs->listCarrierDestinations('omniva', 'EE');
```

**Response (`200 OK`):**

```json
[
  {
    "id": "11701",
    "name": "Ülemiste City pakiautomaat",
    "address": "Lõõtsa 8a, Tallinn",
    "country": "EE",
    "city": "Tallinn",
    "postCode": "11415",
    "latitude": "59.422219",
    "longitude": "24.817721"
  },
  {
    "id": "9002",
    "name": "Kristiine Selver pakiautomaat",
    "address": "Endla 45, Tallinn",
    "country": "EE",
    "city": "Tallinn",
    "postCode": "10615",
    "latitude": "59.422219",
    "longitude": "24.725843"
  }
]
```

**Returns:** `array` — list of pickup point objects. Use `id` as `destination.id` in `createShipment()`.

---

### `createShipment(string $carrier, array $shipments, string $type): mixed`

**Endpoint:** `POST https://shipping.makecommerce.net/shipments`

Additional headers: `MakeCommerce-Carrier: {carrier}`, `MakeCommerce-Method: pickuppoint|courier`.

```php
// Single shipment
$shipment = $mcs->createShipment(
    'omniva',
    [
        [
            'order' => [
                'id'        => '1',
                'reference' => 'ORDER-REF-001',
            ],
            'destination' => [
                'id'      => '11701',  // pickup point ID from listCarrierDestinations()
                'country' => 'EE',
            ],
            'recipient' => [
                'name'  => 'John Smith',
                'phone' => '+37256123123',
                'email' => 'johnsmith@maksekeskus.ee',
            ],
        ]
    ],
    MakeCommerceClient::TYPE_PICKUPPOINT
);

// Multiple shipments — each shipment is one element in the array
$shipments = $mcs->createShipment('omniva', [
    ['order' => [...], 'destination' => [...], 'recipient' => [...]],
    ['order' => [...], 'destination' => [...], 'recipient' => [...]],
], MakeCommerceClient::TYPE_PICKUPPOINT);
```

**`$type` constants:**

| Constant | Value | Description |
|----------|-------|-------------|
| `MakeCommerceClient::TYPE_PICKUPPOINT` | `pickuppoint` | Parcel machine / pickup point delivery |
| `MakeCommerceClient::TYPE_COURIER` | `courier` | Home / address delivery |

**Request body:**

```json
[
  {
    "order": {
      "id": "1",
      "reference": "ORDER-REF-001"
    },
    "destination": {
      "id": "11701",
      "country": "EE"
    },
    "recipient": {
      "name": "John Smith",
      "phone": "+37256123123",
      "email": "johnsmith@maksekeskus.ee"
    }
  }
]
```

**Response (`201 Created`):**

```json
{
  "trackingId": "204138EE568",
  "trackingLink": "https://tracking.makecommerce.net/204138EE568",
  "shipment": {
    "shipmentId": "18b3656d-5a35-4e75-8d46-7d45f80c91db",
    "orderId": "1",
    "reference": "ORDER-REF-001",
    "trackingId": "204138EE568",
    "carrier": "omniva",
    "method": "pickuppoint",
    "shopId": "21d96e14-0b4c-4ff2-a1be-459f0ae82517",
    "instanceId": "my-woocommerce-plugin",
    "destination": {
      "id": "11701",
      "country": "EE"
    },
    "recipient": {
      "name": "John Smith",
      "phone": "+37256123123",
      "email": "johnsmith@maksekeskus.ee"
    },
    "carrierShipmentId": null,
    "carrierTrackingId": null,
    "mediated": null,
    "status": "CREATED",
    "version": 0,
    "created": "2026-05-07T13:29:05",
    "originalCreated": "2026-05-07T13:29:05"
  }
}
```

Key response fields:

| Field | Description |
|-------|-------------|
| `trackingId` | Short tracking code — pass to `getLabel()`, `getShipment()`, `updateShipment()` |
| `trackingLink` | Public tracking URL to share with the customer |
| `shipment.status` | Always `CREATED` immediately after creation |

**Throws:** `MCException` if `$type` is not a valid shipment type.

---

### `getShipments(string $size = '', string $pageToken = ''): mixed`

**Endpoint:** `GET https://shipping.makecommerce.net/shipments?size={size}&pageToken={token}`

No request body.

```php
$result = $mcs->getShipments('20');                              // first page
$result = $mcs->getShipments('20', $result->nextPageToken);     // next page
```

**Response (`200 OK`):**

```json
{
  "shipments": [
    {
      "shipmentId": "18b3656d-5a35-4e75-8d46-7d45f80c91db",
      "trackingId": "204138EE568",
      "carrier": "omniva",
      "method": "pickuppoint",
      "status": "CREATED",
      "created": "2026-05-07T13:29:05"
    }
  ],
  "nextPageToken": "token_abc123",
  "totalCount": 142
}
```

**Returns:** Object containing the shipment list and `nextPageToken` for pagination.

---

### `getShipment(string $trackingId): mixed`

**Endpoint:** `GET https://shipping.makecommerce.net/shipments/{trackingId}`

No request body.

```php
$shipment = $mcs->getShipment('204138EE568');
```

**Response (`200 OK`):**

```json
{
  "shipmentId": "18b3656d-5a35-4e75-8d46-7d45f80c91db",
  "orderId": "1",
  "reference": "ORDER-REF-001",
  "trackingId": "204138EE568",
  "carrier": "omniva",
  "method": "pickuppoint",
  "shopId": "21d96e14-0b4c-4ff2-a1be-459f0ae82517",
  "instanceId": "my-woocommerce-plugin",
  "destination": {
    "id": "11701",
    "country": "EE"
  },
  "recipient": {
    "name": "John Smith",
    "phone": "+37256123123",
    "email": "johnsmith@maksekeskus.ee"
  },
  "carrierShipmentId": "7201234567890",
  "carrierTrackingId": "7201234567890",
  "mediated": null,
  "status": "IN_TRANSIT",
  "version": 2,
  "created": "2026-05-07T13:29:05",
  "originalCreated": "2026-05-07T13:29:05"
}
```

**Returns:** Shipment object.

---

### `updateShipment(string $carrier, array $shipment, string $type, string $trackingId): mixed`

**Endpoint:** `PUT https://shipping.makecommerce.net/shipments/{trackingId}`

Additional headers: `MakeCommerce-Carrier: {carrier}`, `MakeCommerce-Method: pickuppoint|courier`. Request body follows the same structure as `createShipment`.

```php
$updated = $mcs->updateShipment(
    'omniva',
    [
        'order'       => ['id' => '1', 'reference' => 'ORDER-REF-001'],
        'destination' => ['id' => '9002', 'country' => 'EE'],
        'recipient'   => ['name' => 'John Smith', 'phone' => '+37256123123', 'email' => 'johnsmith@maksekeskus.ee'],
    ],
    MakeCommerceClient::TYPE_PICKUPPOINT,
    '204138EE568' // trackingId from createShipment()
);
```

**Request body:** Same structure as `createShipment`.

**Response (`200 OK`):** Same structure as `createShipment`.

**Returns:** Updated shipment object.

---

### `getLabel(string $carrier, string $trackingId, string $type = TYPE_PICKUPPOINT): string`

**Endpoint:** `GET https://shipping.makecommerce.net/shipments/{trackingId}/label`

No request body. Pass the `trackingId` from the `createShipment()` response — not the UUID `shipmentId`.

```php
$shipment = $mcs->createShipment(...);

header('Content-Type: application/pdf');
echo $mcs->getLabel('omniva', $shipment->trackingId); // '204138EE568', not the UUID
```

**Response:** Binary PDF file (`application/pdf`).

**Returns:** `string` — raw PDF bytes. Set `Content-Type: application/pdf` before outputting.

---

### `validateCarrierCredentials(string $carrier, array $credentials): bool`

**Endpoint:** `GET https://shipping.makecommerce.net/authenticate`

Additional headers: `MakeCommerce-Carrier: {carrier}`, `MakeCommerce-Carrier-Credentials: base64(json_encode($credentials))`. No request body.

```php
$valid = $mcs->validateCarrierCredentials('omniva', ['apiKey' => 'CARRIER_API_KEY']);
```

**Response (`200 OK`):**

```
Valid
```

**Returns:** `true` if response is `200 OK` with body `Valid`, `false` otherwise.

---

### `changeSubscriptionPlan(string $subscription): bool`

**Endpoint:** `POST https://api.maksekeskus.ee/v1/subscription/activate`

The subscription string is uppercased automatically.

```php
$success = $mcs->changeSubscriptionPlan('BASIC');
```

**Request body:**

```json
{
  "subscription": "BASIC"
}
```

**Response (`200 OK`):**

```
Success
```

**Returns:** `true` on success.

---

### `deactivateSubscriptionPlan(): bool`

**Endpoint:** `POST https://api.maksekeskus.ee/v1/subscription/deactivate`

```php
$success = $mcs->deactivateSubscriptionPlan();
```

**Response (`200 OK`):**

```
Success
```

**Returns:** `true` on success.

---

## Response Object: MCResponse

`connectShop()` is the only method that returns an `MCResponse` object. All other methods return the decoded response body directly.

| Property | Type | Description |
|----------|------|-------------|
| `$code` | `int` | HTTP status code (`200` or `201`) |
| `$body` | `object\|array` | JSON-decoded response body |
| `$rawBody` | `string` | Raw response body string |
| `$headers` | `array` | Response headers |

```php
$response = $mcs->connectShop(...);

$response->code;        // 200
$response->body->jwt;   // JWT string — pass to getIframeUrl()
$response->rawBody;     // Raw JSON string
$response->headers;     // Array of response headers
```

---

## Error Handling

The SDK throws two exception types:

| Exception | When thrown |
|-----------|-------------|
| `MakeCommerceShipping\SDK\Exception\MCException` | API returned a non-2xx HTTP status, or an invalid shipment type was passed to `createShipment()` / `updateShipment()` |
| `GuzzleHttp\Exception\GuzzleException` | Network-level failure (timeout, DNS, TLS, etc.) |

`MCException` extends PHP's base `Exception`. What each method returns:

| Method | Returns | Example |
|--------|---------|---------|
| `$e->getCode()` | HTTP status code as `int` | `422`, `401`, `404` |
| `$e->getMessage()` | HTTP reason phrase as `string` | `"Unprocessable Entity"`, `"Unauthorized"` |
| `$e->getMcErrorCode()` | Always `null` — not populated by the current SDK | `null` |

> **Note:** The raw API error response body is not accessible through `MCException`. The SDK discards it before throwing. If you need the API error detail, you would need to catch `GuzzleException` at a lower level and read the response body from the Guzzle exception directly.

```php
use MakeCommerceShipping\SDK\Exception\MCException;
use GuzzleHttp\Exception\GuzzleException;

try {
    $shipment = $mcs->createShipment('omniva', $shipmentData, MakeCommerceClient::TYPE_PICKUPPOINT);
} catch (MCException $e) {
    $httpStatus = $e->getCode();     // e.g. 422
    $message    = $e->getMessage();  // e.g. "Unprocessable Entity" — HTTP reason phrase, not API message
} catch (GuzzleException $e) {
    $message = $e->getMessage();
}
```

---

## Complete Example

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use MakeCommerceShipping\SDK\Environment;
use MakeCommerceShipping\SDK\Http\MakeCommerceClient;
use MakeCommerceShipping\SDK\Exception\MCException;
use GuzzleHttp\Exception\GuzzleException;

$appInfo = [
    'module'           => 'MyShippingPlugin',
    'module_version'   => '1.0.0',
    'platform'         => 'WooCommerce',
    'platform_version' => '8.0.0',
];

$mcs = new MakeCommerceClient(
    Environment::TEST,
    'YOUR_SHOP_ID',
    'YOUR_SECRET_KEY',
    'my-woocommerce-plugin',
    $appInfo
);

try {
    // --- REQUIRED FIRST-TIME SETUP (steps 1–3 must be done before anything else) ---

    // Step 1: Connect the shop
    $response = $mcs->connectShop(
        userAgent:  $_SERVER['HTTP_USER_AGENT'],
        remoteAddr: $_SERVER['HTTP_HOST'],
        orderUrl:   'https://myshop.com/order/{id}/view',
    );

    // Step 2: Embed the shipping manager iframe on your settings/onboarding page
    $iframeUrl = $mcs->getIframeUrl($response->body->jwt);
    echo '<iframe src="' . $iframeUrl . '" width="100%" height="720px"></iframe>';

    // Step 3: The merchant fills in their sender address
    // inside the iframe. Nothing to call here — the merchant does this in the browser.
    // The features below are only available after the merchant completes that step.

    // --- NORMAL SDK USAGE (only after setup is complete) ---

    $rates = $mcs->getRates(['weight' => 1500, 'destination' => 'EE']);

    $pickupPoints = $mcs->listCarrierDestinations('omniva', 'EE');

    // Create a pickup point shipment
    $shipment = $mcs->createShipment(
        'omniva',
        [
            [
                'order' => [
                    'id'        => '42',
                    'reference' => 'ORDER-42',
                ],
                'destination' => [
                    'id'      => '9002',
                    'country' => 'EE',
                ],
                'recipient' => [
                    'name'  => 'Jane Doe',
                    'phone' => '+37256000000',
                    'email' => 'jane@example.com',
                ],
            ]
        ],
        MakeCommerceClient::TYPE_PICKUPPOINT
    );

    header('Content-Type: application/pdf');
    echo $mcs->getLabel('omniva', $shipment->trackingId);

} catch (MCException $e) {
    error_log('MakeCommerce API error [' . $e->getCode() . ']: ' . $e->getMessage());
} catch (GuzzleException $e) {
    error_log('Network error: ' . $e->getMessage());
}
```

---

## Development

Start the built-in PHP development server (serves the `sample/` directory at `http://localhost:8080`):

```bash
composer start
```

**Code quality tools:**

```bash
vendor/bin/phpcs               # PSR-12 coding standards check
vendor/bin/phpstan             # Static analysis (level 4)
vendor/bin/phpmd src text md-ruleset.xml  # PHP Mess Detector
```
