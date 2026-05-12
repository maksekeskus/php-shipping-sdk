# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MakeCommerce PHP Shipping SDK v2.0 - A PHP library for integrating with MakeCommerce shipping services. Supports PHP 7.4+ and uses Guzzle for HTTP communication.

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
- Manages three distinct base URLs per environment (shipping, manager, api)
- All public methods correspond to specific API endpoints

**Request Types:**
The SDK routes requests to different base URLs via `REQUEST_TYPE_*` constants:
- `REQUEST_TYPE_SHIPPING` - Shipment and parcel machine endpoints
- `REQUEST_TYPE_MANAGER` - Shop connection and iframe URL generation
- `REQUEST_TYPE_API` - Subscription management

**Environment Configuration** (`src/MakeCommerce/Environment.php`):
Three environments with separate API endpoints: `DEV`, `TEST`, `LIVE`

**Response Handling** (`src/MakeCommerce/Http/MCResponse.php`):
- Wraps PSR-7 responses
- Automatically throws `MCException` for non-200/201 status codes
- Provides both `rawBody` (string) and `body` (json_decoded object/array)

### Key Architectural Patterns

1. **Client Initialization**: Requires environment, shopId, secretKey, instanceId, and appInfo (metadata about the platform/module)
2. **Headers**: Every request includes custom `MakeCommerce-*` headers for shop identification, instance ID, app info, and locale
3. **Shipment Types**: All shipment operations require specifying either `TYPE_PICKUPPOINT` or `TYPE_COURIER`
4. **Carrier Headers**: Carrier-specific operations use `MakeCommerce-Carrier` header instead of URL path parameters

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
- No unit tests currently configured (phpunit script exists but no test files)

## Sample Code

Located in `sample/` directory:
- `index.php` - Demonstrates full SDK usage including connectShop, getRates, listCarrierDestinations, createShipment, and iframe generation
- `label.php` - Label retrieval example

**Important**: Sample code shows that `connectShop()` returns JWT token used for `getIframeUrl()` to embed the shipping manager UI.

## API Endpoint Groups

All endpoints defined in `HttpClientInterface`:
- **PICKUPPOINT_RESOURCES** - Parcel machine/pickup point locations
- **SHIPMENT_RESOURCES** - Shipment CRUD and label generation
- **CARRIER_RESOURCES** - Carrier credential validation
- **RATE_RESOURCES** - Shipping rate calculation
- **MANAGER_RESOURCES** - Shop connection and iframe UI
- **CONFIGURATION_RESOURCES** - Subscription plan management

## Required Setup Sequence

**getRates(), listCarrierDestinations(), createShipment(), and getLabel() will fail until the merchant completes first-time setup.** The sequence is mandatory and cannot be skipped:

1. Call `connectShop()` to register the shop and receive a JWT
2. Call `getIframeUrl($jwt)` and embed the returned URL in an iframe
3. The merchant must open that iframe and save their sender details

Only after step 3 are shipping features usable. When helping someone implement this SDK, always check that setup is complete before writing shipment or rate code.

## Common Pitfalls

**Merchant has not completed first-time setup**

The most common failure. `getRates()`, `listCarrierDestinations()`, `createShipment()`, and `getLabel()` will return API errors until the merchant has opened the iframe and saved their sender address. Before debugging any of those methods, verify setup is complete.

**`connectShop()` returns `MCResponse`, not a decoded body**

`connectShop()` is the only method that returns `MCResponse` directly. Every other method returns the decoded response body. Getting the JWT wrong is the most common code mistake:

```php
// WRONG
$iframeUrl = $mcs->getIframeUrl($mcs->connectShop(...)->jwt);

// CORRECT
$response = $mcs->connectShop(...);
$iframeUrl = $mcs->getIframeUrl($response->body->jwt);
```

## ID Types

`createShipment()` returns a top-level `trackingId` (e.g. `204138EE568`) and a nested `shipment.shipmentId` UUID. All follow-up methods (`getLabel()`, `getShipment()`, `updateShipment()`) take `trackingId`. The UUID is never passed back into the SDK.
