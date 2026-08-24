# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MakeCommerce PHP Shipping SDK v2.0 - A PHP library for integrating with MakeCommerce shipping services. Supports PHP 7.4+ and uses Guzzle for HTTP communication.

**The SDK's public surface is exactly the endpoints documented in the public [Shipping API reference](https://developer.makecommerce.net/shipping-api)**

## Public Test Credentials

Use these against `Environment::TEST` when writing or testing sample code:

| Key | Value |
|-----|-------|
| `shopId` | `3425d8b7-0225-4367-8c6f-16b1aba8d766` |
| `secretKey` | `J5S4lcVjC1QfJec8IQPhHSKeAiEf10bPV7KrHPx9AmIl9nCoEtNtJo63SF0YKpFQ` |
| Publishable key | `79p15UvwBLlZfqmoMY8D8LAjq4CwI8Tn` |

These are intentionally public. Never use them in production code examples — always note they should be replaced with merchant-specific credentials.

## Development Commands

**Linting and Analysis:**
```bash
vendor/bin/phpcs              # Check PSR-12 coding standards
vendor/bin/phpstan            # Run static analysis (level 4)
vendor/bin/phpmd src text md-ruleset.xml  # Run PHP Mess Detector
```

**Development Server:**
```bash
composer start                # Starts PHP dev server at localhost:8080 serving sample/ directory
```

## Architecture

### Core Components

**Main Client** (`src/MakeCommerce/Http/MakeCommerceClient.php`):
- Single entry point for all SDK operations
- Handles authentication via HTTP Basic Auth (shopId + secretKey)
- Every API request goes to a single base URL, the shipping host. The manager URL is only
  used by `getIframeUrl()` to build a string; the SDK never calls it.
- All public methods correspond to specific API endpoints

**Environment Configuration** (`src/MakeCommerce/Environment.php`):
Two environments, each with a shipping and a manager URL: `TEST` and `LIVE` — the only servers
the API reference documents. An unknown environment string throws `MCException` from the
constructor. Do not add a DEV tier back.

**Response Handling** (`src/MakeCommerce/Http/MCResponse.php`):
- Wraps PSR-7 responses; a plain value object that never throws
- Decodes `body` only when the response `Content-Type` is JSON, so label PDFs stay untouched
- Provides `code`, `rawBody` (string), `body` (decoded or `null`), `headers`, and `message`

**Error Handling** (`src/MakeCommerce/Exception/MCException.php`):
- The Guzzle client sets `http_errors => false`, so error responses reach the SDK rather than
  being wrapped by Guzzle. **Do not remove that option** — without it Guzzle throws before
  `MCResponse` is built and the API's error message is lost.
- `makeApiRequest()` accepts any 2xx and maps everything else onto `MCException`, carrying the
  API's `message` and the full response via `getResponse()`
- `GuzzleException` now only signals network-level failure

**Webhooks:** `MakeCommerceClient::validateWebhook()` verifies the MAC on a shipment status
notification. It lives on the client because the client already holds the secret key. It makes
no HTTP request, and it must always use `hash_equals` — never `==` on the hex string.

### Key Architectural Patterns

1. **Client Initialization**: Requires environment, shopId, secretKey and instanceId. `appInfo` (metadata about the platform/module) is optional.
2. **Headers**: Every request includes lowercase `makecommerce-*` headers for instance ID, app info and locale. Only `makecommerce-shop-instance` is required by the API; keep header names lowercase to match the reference.
3. **Delivery Methods**: All shipment operations require either `TYPE_PICKUPPOINT` or `TYPE_COURIER`, validated locally before the request is sent
4. **Carrier Headers**: Carrier-specific operations use the `makecommerce-carrier` header instead of URL path parameters, plus `makecommerce-method` for create and update

### Namespace Structure

- `MakeCommerceShipping\SDK` - Root namespace
- `MakeCommerceShipping\SDK\Http` - HTTP client and response classes
- `MakeCommerceShipping\SDK\Exception` - Custom exceptions

## Code Quality Standards

- **Coding Standard**: PSR-12 with additional Generic.Arrays.DisallowLongArraySyntax rule
- **Static Analysis**: PHPStan level 4
- **PHPMD Rules**: Full ruleset including unusedcode, codesize, naming, design, controversial, and cleancode
- **Variable Names**: Maximum length 25 characters (customized PHPMD rule)

## Testing & CI

GitHub Actions workflow (`.github/workflows/tests.yml`) runs on PHP 7.4, 8.0, 8.1, 8.2:
- PHP 8.1 matrix includes analysis jobs (phpcs + phpstan)
- No unit tests configured, and no `composer test` script: phpunit is not a dependency and no
  test files exist. Do not add the script without also adding the dependency and a test.
- PHPMD is not run in CI, so its findings are advisory

## Sample Code

Located in `sample/` directory:
- `index.php` - Demonstrates full SDK usage including connectShop, getRates, listCarrierDestinations, createShipment, and iframe generation
- `label.php` - Label retrieval example

**Important**: Sample code shows that `connectShop()` returns JWT token used for `getIframeUrl()` to embed the shipping manager UI.

## API Endpoint Groups

All endpoints defined in `HttpClientInterface`, which is deliberately a constant bag —
merchants reference `MakeCommerceClient::TYPE_PICKUPPOINT` through it, so do not restructure it:
- **SHOP_RESOURCES** - `/connect`. Despite being part of the iframe setup flow, this is served
  by the **shipping** host, not the manager host — do not move it under `MANAGER_RESOURCES`.
- **RATE_RESOURCES** - Shipping rate calculation
- **PICKUPPOINT_RESOURCES** - Parcel machine/pickup point locations
- **SHIPMENT_RESOURCES** - Shipment CRUD and label generation
- **MANAGER_RESOURCES** - Iframe UI path only (never requested by the SDK)

## Required Setup Sequence

**getRates(), listCarrierDestinations(), createShipment(), and getLabel() will fail until the merchant completes first-time setup.** The sequence is mandatory and cannot be skipped:

1. Call `connectShop()` to register the shop and receive a JWT
2. Call `getIframeUrl($jwt)` and embed the returned URL in an iframe
3. The merchant must open that iframe and save their sender details

Only after step 3 are shipping features usable. When helping someone implement this SDK, always check that setup is complete before writing shipment or rate code.

## Common Pitfalls

**Merchant has not completed first-time setup**

The most common failure. Before debugging any of these methods, verify setup is complete. Note
the failures do not all look like errors:

- `getRates()` returns `200 OK` with **empty** `pickuppoint` and `courier` arrays. There is no
  exception to catch — empty rates almost always means setup is incomplete, not a bug.
- `createShipment()` can succeed while the carrier never accepts the shipment. The shipment
  then has `status: FAILED` and `carrierTrackingId: null`, and `getLabel()` fails with a 500
  `"Failed to create a shipment with carrier"`.
- `listCarrierDestinations()` works regardless, since pickup point lists are not shop-specific.

**`connectShop()` returns `MCResponse`, not a decoded body**

`connectShop()` is the only method that returns `MCResponse` directly. Every other method returns the decoded response body. Getting the JWT wrong is the most common code mistake:

```php
// WRONG
$iframeUrl = $mcs->getIframeUrl($mcs->connectShop(...)->jwt);

// CORRECT
$response = $mcs->connectShop('https://myshop.com');
$iframeUrl = $mcs->getIframeUrl($response->body->jwt);
```

## ID Types

`createShipment()` returns a top-level `trackingId` (e.g. `204138EE568`) and a nested `shipment.shipmentId` UUID. All follow-up methods (`getLabel()`, `getShipment()`, `updateShipment()`) take `trackingId`. The UUID is never passed back into the SDK.
