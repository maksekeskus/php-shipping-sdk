# MakeCommerce PHP Shipping SDK

**Package:** `maksekeskus/php-shipping-sdk` | **Version:** 1.5.5 | **PHP:** >= 7.4 | **License:** MIT

A PHP client library for integrating with MakeCommerce shipping services. Supports shipment creation, rate calculation, pickup point lookups, label retrieval, carrier credential validation, and the embedded shipping manager UI.

---

## Table of Contents

1. [Installation](#installation)
2. [Credentials](#credentials)
3. [Client Initialization](#client-initialization)
4. [Core Flow: Shop Connection + Iframe](#core-flow-shop-connection--iframe)
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
    'your-instance-id',   // Unique string identifying this integration instance
    $appInfo
);
```

### Constructor Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$environment` | `string` | Yes | `Environment::TEST` or `Environment::LIVE` |
| `$shopId` | `string` | Yes | Shop identifier from merchant portal |
| `$shopSecret` | `string` | Yes | Secret key from merchant portal |
| `$instanceId` | `string` | Yes | Unique identifier for this integration instance (e.g. `'woocommerce-plugin'`) |
| `$appInfo` | `array` | Yes | Platform metadata — see keys below |

### `$appInfo` Keys

| Key | Type | Description |
|-----|------|-------------|
| `module` | `string` | Name of your module/plugin |
| `module_version` | `string` | Version of your module/plugin |
| `platform` | `string` | E-commerce platform name (e.g. `'WooCommerce'`) |
| `platform_version` | `string` | Platform version |

### Optional: Locale

```php
$mcs->setLocale('et'); // Default is 'en'
```

Affects the language of the embedded shipping manager iframe.

---

## Core Flow: Shop Connection + Iframe

Before embedding the shipping manager UI, you must call `connectShop()` to obtain a JWT, then pass it to `getIframeUrl()`.

```php
// PHP 8.0+ named arguments — recommended so parameter order does not matter
$response = $mcs->connectShop(
    userAgent:   $_SERVER['HTTP_USER_AGENT'],
    remoteAddr:  $_SERVER['HTTP_HOST'],
    orderUrl:    'https://myshop.com/order/{id}/view', // optional
    webhookUrl:  'https://myshop.com/webhook',         // optional
);

$iframeUrl = $mcs->getIframeUrl($response->body->jwt);

echo '<iframe src="' . $iframeUrl . '" width="100%" height="720px"></iframe>';
```

> **Note:** The `remoteAddr` parameter must use HTTPS for all production domains. HTTP is only allowed for `localhost`.

---

## Method Reference

### `getRates(array $data, array &$location = []): object`

Fetches available shipping rates for a given weight and destination.

```php
$location = [];
$rates = $mcs->getRates(
    [
        'weight'      => 2500,  // Weight in grams (automatically rounded to int)
        'destination' => 'EE',  // 2-letter ISO country code
    ],
    $location // Optionally filled with location data from response headers
);
```

**Returns:** `object` — Available rates from the API response body.

---

### `listCarrierDestinations(string $carrier, string $country): array`

Returns all pickup point locations for a given carrier and country.

```php
$pickupPoints = $mcs->listCarrierDestinations('omniva', 'EE');
```

**Returns:** `array` — List of pickup point/destination objects.

> Country code is converted to lowercase internally.

---

### `createShipment(string $carrier, array $shipments, string $type): mixed`

Creates one or more shipments.

```php
$shipment = $mcs->createShipment(
    'omniva',
    [
        [
            'order' => [
                'id'        => '1',
                'reference' => 'ORDER-REF-001',
            ],
        'destination' => [
                'id'      => '11701',  // Pickup point ID (for TYPE_PICKUPPOINT)
                'country' => 'EE',
            ],
            'recipient' => [
                'name'  => 'John Smith',
                'phone' => '+37256123123',
                'email' => 'johnsmith@maksekeskus.ee',
            ],
        ]
    ],
    MakeCommerceClient::TYPE_PICKUPPOINT  // or MakeCommerceClient::TYPE_COURIER
);

echo $shipment->trackingId;
```

**`$type` constants:**

| Constant | Value | Description |
|----------|-------|-------------|
| `MakeCommerceClient::TYPE_PICKUPPOINT` | `'pickuppoint'` | Delivery to a parcel machine / pickup point |
| `MakeCommerceClient::TYPE_COURIER` | `'courier'` | Home/address delivery |

**Returns:** Created shipment object. Example response payload:

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
        "instanceId": "your-instance-id",
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

Key fields to use after creation:

| Field | Description |
|-------|-------------|
| `trackingId` | Short tracking code — use to retrieve the label via `getLabel()` |
| `trackingLink` | Public tracking URL to share with the customer |
| `shipment.shipmentId` | Full UUID of the shipment — use for `getShipment()` / `updateShipment()` |
| `shipment.status` | Initial status is always `CREATED` |

**Throws:** `MCException` if `$type` is not a valid shipment type.

---

### `getShipments(string $size = '', string $pageToken = ''): mixed`

Retrieves a paginated list of all shipments.

```php
// First page
$result = $mcs->getShipments('20');

// Next page
$result = $mcs->getShipments('20', $result->nextPageToken);
```

**Returns:** Object/array containing the shipment list and pagination token.

---

### `getShipment(string $shipmentId): mixed`

Retrieves a single shipment by its ID.

```php
$shipment = $mcs->getShipment('SHIPMENT_ID');
```

**Returns:** Shipment object.

---

### `updateShipment(string $carrier, array $shipment, string $type, string $shipmentId): mixed`

Updates an existing shipment. The `$shipment` array follows the same structure as `createShipment`.

```php
$updated = $mcs->updateShipment(
    'omniva',
    [ /* shipment data */ ],
    MakeCommerceClient::TYPE_PICKUPPOINT,
    'SHIPMENT_ID'
);
```

**Returns:** Updated shipment object.

---

### `getLabel(string $carrier, string $shipmentId, string $type = TYPE_PICKUPPOINT): string`

Retrieves the shipping label for a shipment as raw PDF bytes.

```php
header('Content-Type: application/pdf');
echo $mcs->getLabel('omniva', 'SHIPMENT_ID');
```

**Returns:** `string` — Raw PDF content. Set `Content-Type: application/pdf` before outputting.

---

### `validateCarrierCredentials(string $carrier, array $credentials): bool`

Validates carrier API credentials.

```php
$valid = $mcs->validateCarrierCredentials('omniva', [
    'apiKey' => 'CARRIER_API_KEY',
]);
```

**Returns:** `true` if credentials are valid, `false` otherwise.

---

### `changeSubscriptionPlan(string $subscription): bool`

Activates or changes the subscription plan.

```php
$success = $mcs->changeSubscriptionPlan('BASIC');
```

**Returns:** `true` on success.

> Subscription string is converted to uppercase internally.

---

### `deactivateSubscriptionPlan(): bool`

Deactivates the active subscription plan.

```php
$success = $mcs->deactivateSubscriptionPlan();
```

**Returns:** `true` on success.

---

## Response Object: MCResponse

`connectShop()` returns an `MCResponse` object directly. All other methods return the decoded `body` from the response.

| Property | Type | Description |
|----------|------|-------------|
| `$code` | `int` | HTTP status code (`200` or `201`) |
| `$body` | `object\|array` | JSON-decoded response body |
| `$rawBody` | `string` | Raw response body string |
| `$headers` | `array` | Response headers |

**Example:**

```php
$response = $mcs->connectShop(...);

$response->code;       // 200
$response->body->jwt;  // JWT string for getIframeUrl()
$response->rawBody;    // Raw JSON string
$response->headers;    // Array of response headers
```

---

## Error Handling

The SDK throws two exception types:

| Exception | When thrown |
|-----------|-------------|
| `MakeCommerceShipping\SDK\Exception\MCException` | API returned a non-2xx HTTP status, or an invalid shipment type was passed |
| `GuzzleHttp\Exception\GuzzleException` | Network-level failure (timeout, DNS, TLS, etc.) |

`MCException` extends PHP's base `Exception` and adds `getMcErrorCode()` for API-specific error codes.

```php
use MakeCommerceShipping\SDK\Exception\MCException;
use GuzzleHttp\Exception\GuzzleException;

try {
    $shipment = $mcs->createShipment('omniva', $shipmentData, MakeCommerceClient::TYPE_PICKUPPOINT);
} catch (MCException $e) {
    $httpStatus   = $e->getCode();        // e.g. 422
    $message      = $e->getMessage();     // Human-readable error
    $apiErrorCode = $e->getMcErrorCode(); // MakeCommerce-specific error code, if any
} catch (GuzzleException $e) {
    // Handle network/transport failure
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
    // 1. Connect shop and embed the shipping manager UI
    $response = $mcs->connectShop(
        userAgent:  $_SERVER['HTTP_USER_AGENT'],
        remoteAddr: $_SERVER['HTTP_HOST'],
        orderUrl:   'https://myshop.com/order/{id}/view',
    );
    $iframeUrl = $mcs->getIframeUrl($response->body->jwt);
    echo '<iframe src="' . $iframeUrl . '" width="100%" height="720px"></iframe>';

    // 2. Get shipping rates
    $rates = $mcs->getRates(['weight' => 1500, 'destination' => 'EE']);

    // 3. List pickup points
    $pickupPoints = $mcs->listCarrierDestinations('omniva', 'EE');

    // 4. Create a pickup point shipment
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

    // 5. Retrieve the label (outputs PDF)
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
