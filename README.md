# MakeCommerce PHP Shipping SDK

**Package:** `maksekeskus/php-shipping-sdk` | **Version:** 2.0.0 | **PHP:** >= 7.4 | **License:** MIT

A PHP client library for integrating with MakeCommerce shipping services. Supports shipment creation, rate calculation, pickup point lookups, label retrieval, shipment status webhooks, and the embedded shipping manager UI.

The SDK maps one-to-one onto the [public Shipping API](https://developer.makecommerce.net/shipping-api).

> **Before you can create shipments, fetch rates, print labels, you must complete the one-time shop setup described in [Required First-Time Setup](#required-first-time-setup) below.** Skipping it will cause API errors — the platform has no sender address or carrier configuration to work with yet.

---

## Table of Contents

1. [Installation](#installation)
2. [Credentials](#credentials)
3. [Client Initialization](#client-initialization)
4. [Required First-Time Setup](#required-first-time-setup)
5. [Method Reference](#method-reference)
6. [Shipment Statuses](#shipment-statuses)
7. [Shipment Status Webhooks](#shipment-status-webhooks)
8. [Response Object](#response-object-mcresponse)
9. [Error Handling](#error-handling)
10. [Complete Example](#complete-example)
11. [Development](#development)

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
    $appInfo               // Optional
);
```

### Constructor Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$environment` | `string` | Yes | `Environment::TEST` or `Environment::LIVE`. Any other value throws `MCException`. |
| `$shopId` | `string` | Yes | Shop identifier from merchant portal |
| `$shopSecret` | `string` | Yes | Secret key from merchant portal |
| `$instanceId` | `string` | Yes | Unique identifier for this specific shop installation. Must be a random UUID or unique string generated **once** per installation and stored persistently. Every request from the same shop installation must use the **same** value — a new installation (or a different e-commerce platform) must use a different value. Never regenerate it on each request. |
| `$appInfo` | `array` | No | Platform metadata — see keys below. Optional; when omitted no app-info header is sent. |

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

**Endpoint:** `POST https://shipping.makecommerce.net/connect`

Call `connectShop()` to register the shop with the platform and receive a short-lived JWT.

```php
$response = $mcs->connectShop(
    'https://myshop.com',                    // REMOTE_ADDR — the site that will host the iframe
    'https://myshop.com/order/{id}/view',    // optional orderUrl
    'https://myshop.com/webhook'             // optional webhookUrl
);
```

**Request body:**

```json
{
  "REMOTE_ADDR": "https://myshop.com",
  "orderUrl":    "https://myshop.com/order/{id}/view",
  "webhookUrl":  "https://myshop.com/webhook"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `REMOTE_ADDR` | Yes | The address permitted to embed the iframe. The platform resolves the host from this value, so a full URL (`https://myshop.com`) and a bare host (`myshop.com`) both work. The SDK sends it through unchanged. |
| `orderUrl` | No | URL template for your order pages. `{id}` is replaced with the order ID you pass when creating a shipment, so the shipping manager can link straight to the order. |
| `webhookUrl` | No | Destination for [shipment status webhooks](#shipment-status-webhooks). |

**Response (`200 OK`):**

```json
{
  "jwt": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzaG9wSWQiOiJZT1VSX1NIT1BfSUQiLCJpbnN0YW5jZUlkIjoibXktd29vY29tbWVyY2UtcGx1Z2luIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"
}
```

Pass `$response->body->jwt` to `getIframeUrl()` in the next step.

> **Note:** A full HTTPS URL is the clearest thing to pass, e.g. `https://myshop.com`. The platform parses the host out of whatever you send, so `$_SERVER['HTTP_HOST']` works too.

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
| `Authorization` | `Basic base64(shopId:secretKey)` — set by Guzzle from the credentials |
| `accept` | `application/json` |
| `content-type` | `application/json` |
| `makecommerce-shop-instance` | Your instance ID — required by the API |
| `makecommerce-shipping-appinfo` | `base64(json_encode($appInfo))`. Omitted when no `$appInfo` was supplied. |
| `makecommerce-user-locale` | Locale string (default: `en`) |

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

Every API endpoint lives on `{shipping}`. `{manager}` is only used to build the iframe URL and is never called by the SDK.

### Quick reference

| Method | HTTP | Endpoint | Returns |
|--------|------|----------|---------|
| `connectShop()` | POST | `{shipping}/connect` | `MCResponse` — JWT at `->body->jwt` |
| `getIframeUrl()` | — | builds `{manager}/public/ui/?jwt=...` | `string` URL |
| `getRates()` | POST | `{shipping}/rates` | `object` |
| `listCarrierDestinations()` | GET | `{shipping}/pickuppoint/{country}` | `array` |
| `createShipment()` | POST | `{shipping}/shipments` | `object` — use `->trackingId` for `getLabel()` / `getShipment()` / `updateShipment()` |
| `getShipments()` | GET | `{shipping}/shipments?size&pageToken` | `object` |
| `getShipment()` | GET | `{shipping}/shipments/{trackingId}` | `object` |
| `updateShipment()` | PUT | `{shipping}/shipments/{trackingId}` | `object` |
| `getLabel()` | GET | `{shipping}/shipments/{trackingId}/label` | raw PDF `string` |

These nine methods are the SDK's entire API surface, and they map one-to-one onto the endpoints documented in the [Shipping API reference](https://developer.makecommerce.net/shipping-api).

---

### `getRates(array $data): object`

**Endpoint:** `POST https://shipping.makecommerce.net/rates`

```php
$rates = $mcs->getRates([
    'weight'      => 2500,  // grams — rounded to int automatically
    'destination' => 'EE',  // 2-letter ISO country code
]);
```

**Request body:** (`weight` in grams)

```json
{
  "weight": 2500,
  "destination": "EE"
}
```

**Response (`200 OK`):** rates grouped by delivery method.

```json
{
  "pickuppoint": [
    {
      "carrier": "omniva",
      "title": "Omniva pickup point",
      "price": 240,
      "image": "https://static.maksekeskus.ee/img/shipping/omniva.svg"
    },
    {
      "carrier": "dpd",
      "title": "DPD pickup point",
      "price": 240,
      "image": "https://static.maksekeskus.ee/img/shipping/dpd.svg"
    }
  ],
  "courier": [
    {
      "carrier": "omniva",
      "title": "Omniva courier",
      "price": 650,
      "image": "https://static.maksekeskus.ee/img/shipping/omniva.svg"
    }
  ]
}
```

**Returns:** `object` with a `pickuppoint` and a `courier` array. Each rate has `carrier`, `title`, `price` and `image`. `price` is in **cents** (e.g. `240` = €2.40). The key a rate appears under is the `$method` you pass to `createShipment()`.

> **Both arrays empty?** That means the shop has no active carrier methods yet — the merchant has not completed [first-time setup](#required-first-time-setup). The call still succeeds with `200 OK`; it is not an error you can catch.

---

### `listCarrierDestinations(string $carrier, string $country): array`

**Endpoint:** `GET https://shipping.makecommerce.net/pickuppoint/{country}`

Additional header: `makecommerce-carrier: {carrier}`. Country code is lowercased automatically. No request body.

```php
$pickupPoints = $mcs->listCarrierDestinations('omniva', 'EE');
```

**Response (`200 OK`):**

```json
[
  {
    "name": "Tallinna Akadeemia Coop Konsumi pakiautomaat",
    "city": "Tallinn",
    "id": "96003",
    "address": "Akadeemia tee 35",
    "zip": "96003",
    "mc_address_zip": "12618",
    "x": "24.65561300",
    "y": "59.40337000"
  },
  {
    "name": "Lasnamäe Mustakivi Maxima pakiautomaat",
    "city": "Tallinn",
    "id": "13912",
    "address": "Mustakivi tee 13",
    "zip": "13912",
    "mc_address_zip": "13912",
    "x": "24.86743200",
    "y": "59.43789100"
  }
]
```

| Field | Description |
|-------|-------------|
| `id` | Pickup point identifier — use as `destination.id` in `createShipment()` |
| `name` | Pickup point name |
| `city`, `address` | Location, for display |
| `zip` | Postal code as provided by the carrier |
| `mc_address_zip` | The postal code as resolved by MakeCommerce |
| `x`, `y` | Longitude and latitude |

**Returns:** `array` — list of pickup point objects.

---

### `createShipment(string $carrier, string $method, array $shipments): object`

**Endpoint:** `POST https://shipping.makecommerce.net/shipments`

Additional headers: `makecommerce-carrier: {carrier}`, `makecommerce-method: pickuppoint|courier`.

```php
// Single shipment
$shipment = $mcs->createShipment(
    'omniva',
    MakeCommerceClient::TYPE_PICKUPPOINT,
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
    ]
);

// Multiple shipments — each shipment is one element in the array
$shipments = $mcs->createShipment('omniva', MakeCommerceClient::TYPE_PICKUPPOINT, [
    ['order' => [...], 'destination' => [...], 'recipient' => [...]],
    ['order' => [...], 'destination' => [...], 'recipient' => [...]],
]);
```

**A courier shipment** uses the same call with a full address instead of a pickup point ID:

```php
$shipment = $mcs->createShipment(
    'omniva',
    MakeCommerceClient::TYPE_COURIER,
    [
        [
            'order'       => ['id' => '1', 'reference' => 'ORDER-REF-001'],
            'destination' => [
                'zip'     => '10134',
                'country' => 'EE',
                'county'  => 'Harjumaa',
                'city'    => 'Tallinn',
                'street'  => 'Vana Lõuna 12',
            ],
            'recipient'   => [
                'name'  => 'John Smith',
                'phone' => '+37256123123',
                'email' => 'johnsmith@maksekeskus.ee',
            ],
        ]
    ]
);
```

**`$method` constants:**

| Constant | Value | `destination` shape |
|----------|-------|---------------------|
| `MakeCommerceClient::TYPE_PICKUPPOINT` | `pickuppoint` | `id` (from `listCarrierDestinations()`) + `country` |
| `MakeCommerceClient::TYPE_COURIER` | `courier` | `zip` + `country`, plus optional `county`, `city`, `street` |

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
| `shipment.status` | `CREATED` immediately after creation — see [Shipment Statuses](#shipment-statuses) |

**Throws:** `MCException` if `$method` is not `pickuppoint` or `courier`. This is validated locally, before any request is sent.

---

### `getShipments(?int $size = null, ?string $pageToken = null): object`

**Endpoint:** `GET https://shipping.makecommerce.net/shipments?size={size}&pageToken={token}`

No request body. Query parameters are only appended when supplied — calling `getShipments()` with no arguments requests `/shipments` with no query string at all.

```php
$result = $mcs->getShipments();                             // server default page size
$result = $mcs->getShipments(20);                           // first page of 20
$result = $mcs->getShipments(20, $result->nextPageToken);   // next page
```

**Response (`200 OK`):**

```json
{
  "items": [
    {
      "shipmentId": "18b3656d-5a35-4e75-8d46-7d45f80c91db",
      "orderId": "1",
      "reference": "ORDER-REF-001",
      "trackingId": "204138EE568",
      "carrier": "omniva",
      "method": "pickuppoint",
      "status": "CREATED",
      "version": 0,
      "created": "2026-05-07T13:29:05"
    }
  ],
  "nextPageToken": "token_abc123",
  "previousPageToken": null,
  "count": 142
}
```

| Field | Description |
|-------|-------------|
| `items` | Array of shipment objects, same shape as `getShipment()` |
| `nextPageToken` | Pass to the next `getShipments()` call. `null` on the last page. |
| `previousPageToken` | `null` on the first page |
| `count` | Total number of shipments |

**Returns:** `object`.

> A shipment that has been updated appears once per version, so the same `trackingId` can show up more than once in `items`.

---

### `getShipment(string $trackingId): object`

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

**Returns:** `object` — the shipment. See [Shipment Statuses](#shipment-statuses) for the `status` values.

**Throws:** `MCException` with code `404` if no shipment has that tracking ID.

---

### `updateShipment(string $trackingId, string $carrier, string $method, array $shipments): object`

**Endpoint:** `PUT https://shipping.makecommerce.net/shipments/{trackingId}`

Additional headers: `makecommerce-carrier: {carrier}`, `makecommerce-method: pickuppoint|courier`. The request body is an array of shipments, exactly as in `createShipment()`.

```php
$updated = $mcs->updateShipment(
    '204138EE568', // trackingId from createShipment()
    'omniva',
    MakeCommerceClient::TYPE_PICKUPPOINT,
    [
        [
            'order'       => ['id' => '1', 'reference' => 'ORDER-REF-001'],
            'destination' => ['id' => '9002', 'country' => 'EE'],
            'recipient'   => ['name' => 'John Smith', 'phone' => '+37256123123', 'email' => 'johnsmith@maksekeskus.ee'],
        ]
    ]
);
```

**Request body:** Same structure as `createShipment` — a list, even for a single shipment.

**Response (`200 OK`):** Same structure as `createShipment`. The `shipment.version` field increments on each update.

**Returns:** `object` — the updated shipment.

---

### `getLabel(string $trackingId): string`

**Endpoint:** `GET https://shipping.makecommerce.net/shipments/{trackingId}/label`

No request body, and no carrier or method — the platform resolves both from the shipment.

```php
$shipment = $mcs->createShipment(...);

header('Content-Type: application/pdf');
echo $mcs->getLabel($shipment->trackingId); // '204138EE568', not the UUID
```

**Response:** Binary PDF file (`application/pdf`).

**Returns:** `string` — raw PDF bytes. Set `Content-Type: application/pdf` before outputting. Because the body is not JSON, `MCResponse::$body` is `null` for this endpoint; the bytes are in `$rawBody`.

**Throws:** `MCException` with code `404` if the shipment does not exist, or `500` if the carrier never accepted the shipment — a shipment whose `status` is `FAILED` has no label to print.

---

## Shipment Statuses

A shipment's `status` field is passed through as the API sent it. The SDK deliberately does not
enumerate the values, so a status added on the platform side needs no SDK release to be usable —
compare against the strings, or see the
[Shipping API reference](https://developer.makecommerce.net/shipping-api) for the current set.

One value is worth special handling: **`FAILED`** means the carrier rejected the shipment, so it
has no label and cannot be dispatched.

```php
$shipment = $mcs->getShipment('204138EE568');

if ($shipment->status === 'FAILED') {
    // getLabel() will fail for this shipment.
}
```

---

## Shipment Status Webhooks

If you passed a `webhookUrl` to `connectShop()`, MakeCommerce sends a form-encoded `POST`
to it whenever a shipment's status changes:

| Field | Description |
|-------|-------------|
| `json` | A JSON-encoded string with `order_id`, `tracking_id` and `shipment_status` |
| `mac` | Signature over `json`, for validating authenticity |

The signature is `UPPERCASE(HEX(SHA-512(json . secretKey)))`. **Always validate it before
acting on a notification** — otherwise anyone who knows your webhook URL can move your
order statuses. `validateWebhook()` does this for you, using a timing-safe comparison:

```php
// Verify first, decode second.
if (!$mcs->validateWebhook($_POST)) {
    http_response_code(400);
    return; // MAC mismatch — discard the notification
}

$update = json_decode($_POST['json']);

$update->order_id;         // the order ID you passed when creating the shipment
$update->tracking_id;      // e.g. '204219EE566'
$update->shipment_status;  // numeric status code, e.g. '242'
```

### `validateWebhook(array $payload): bool`

Pass the received POST fields. Returns `true` only when `mac` matches the signature computed
over `json` with your secret key; `false` for a mismatch, or when either field is missing or
not a string. It performs no HTTP request.

> **`shipment_status` is a numeric code here**, not one of the string statuses above —
> `1xx` initial, `2xx` in transit, `3xx` delivered, `4xx` return, `5xx` problem states.
> Polling can stop once you receive a `3xx`. See the
> [webhook reference](https://developer.makecommerce.net/guides/shipping/ShippingWebhooks)
> for the full code table.

---

## Response Object: MCResponse

`connectShop()` is the only method that returns an `MCResponse` object. All other methods return the decoded response body directly.

| Property | Type | Description |
|----------|------|-------------|
| `$code` | `int` | HTTP status code |
| `$body` | `object\|array\|null` | Decoded body. `null` when the response is not JSON (e.g. a label PDF) or the JSON is malformed. |
| `$rawBody` | `string` | Raw response body string |
| `$headers` | `array` | Response headers |
| `$message` | `string\|null` | The API's error message, when the body carries one |

```php
$response = $mcs->connectShop('https://myshop.com');

$response->code;        // 200
$response->body->jwt;   // JWT string — pass to getIframeUrl()
$response->rawBody;     // Raw JSON string
$response->headers;     // Array of response headers
```

`MCResponse` is a plain value object — it never throws. Turning a non-2xx status into an
exception is the client's job, so you can also reach the response behind a failure via
`MCException::getResponse()`.

---

## Error Handling

Every non-2xx response becomes an `MCException`. The SDK configures Guzzle with
`http_errors => false` so error responses reach the SDK intact and the API's own message
survives — you do **not** need to catch `GuzzleException` to read an API error.

| Exception | When thrown |
|-----------|-------------|
| `MakeCommerceShipping\SDK\Exception\MCException` | The API returned a non-2xx status, or the SDK rejected the input locally (unknown environment, invalid `$method`) |
| `GuzzleHttp\Exception\GuzzleException` | Network-level failure only — timeout, DNS, TLS |

`MCException` extends PHP's base `Exception`:

| Method | Returns |
|--------|---------|
| `$e->getCode()` | HTTP status code as `int` — `400`, `401`, `404`, `500`. `400` for local validation failures. |
| `$e->getMessage()` | The API's `message` field when it sent one, otherwise the HTTP reason phrase |
| `$e->getResponse()` | The `MCResponse` behind the failure, or `null` for a local validation failure |
| `$e->getMcErrorCode()` | Reserved for future use — always `null` |

```php
use MakeCommerceShipping\SDK\Exception\MCException;
use GuzzleHttp\Exception\GuzzleException;

try {
    $shipment = $mcs->createShipment('omniva', MakeCommerceClient::TYPE_PICKUPPOINT, $shipmentData);
} catch (MCException $e) {
    $status  = $e->getCode();     // e.g. 401
    $message = $e->getMessage();  // e.g. "Invalid credentials" — the API's own message

    // The full response, when the failure came from the API
    if ($response = $e->getResponse()) {
        $response->rawBody;  // the raw error body
        $response->body;     // decoded, e.g. ->message
        $response->headers;
    }
} catch (GuzzleException $e) {
    // Network failure — the request never got an HTTP response
    error_log('Network error: ' . $e->getMessage());
}
```

**Checking for a missing shipment.** `getShipment()` and `getLabel()` throw rather than
returning `null`, so test the code:

```php
try {
    $shipment = $mcs->getShipment($trackingId);
} catch (MCException $e) {
    if ($e->getCode() === 404) {
        // No such shipment
    } else {
        throw $e;
    }
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

    // Step 1: Connect the shop.
    $response = $mcs->connectShop(
        'https://myshop.com',
        'https://myshop.com/order/{id}/view',  // optional orderUrl
        'https://myshop.com/webhook'           // optional webhookUrl
    );

    // Step 2: Embed the shipping manager iframe on your settings/onboarding page
    $iframeUrl = $mcs->getIframeUrl($response->body->jwt);
    echo '<iframe src="' . $iframeUrl . '" width="100%" height="720px"></iframe>';

    // Step 3: The merchant fills in their sender address
    // inside the iframe. Nothing to call here — the merchant does this in the browser.
    // The features below are only available after the merchant completes that step.

    // --- NORMAL SDK USAGE (only after setup is complete) ---

    $rates = $mcs->getRates(['weight' => 1500, 'destination' => 'EE']); // weight in grams; price in response is in cents

    $pickupPoints = $mcs->listCarrierDestinations('omniva', 'EE');

    // Create a pickup point shipment
    $shipment = $mcs->createShipment(
        'omniva',
        MakeCommerceClient::TYPE_PICKUPPOINT,
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
        ]
    );

    header('Content-Type: application/pdf');
    echo $mcs->getLabel($shipment->trackingId);

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
