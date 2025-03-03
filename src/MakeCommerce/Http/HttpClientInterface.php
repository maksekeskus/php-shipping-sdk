<?php

namespace MakeCommerceShipping\SDK\Http;

interface HttpClientInterface
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const DEV_BASE_URI = 'https://shipping.dev.makecommerce.net';
    public const TEST_BASE_URI = 'https://shipping.test.makecommerce.net';
    public const LIVE_BASE_URI = 'https://shipping.makecommerce.net';
    public const DEV_MANAGER_URI = 'https://shipping-manager.dev.makecommerce.net';
    public const TEST_MANAGER_URI = 'https://shipping-manager.test.makecommerce.net';
    public const LIVE_MANAGER_URI = 'https://shipping-manager.makecommerce.net';

    public const PICKUPPOINT_RESOURCES = [
        'listPickupPoints' => '/pickuppoint',
        'listCarrierDestinations' => '/pickuppoint/{country}'
    ];

    public const COURIER_RESOURCES = [
        'listCouriers' => '/courier'
    ];

    public const SHIPMENT_RESOURCES = [
        'shipments' => '/shipments',
        'shipment' => '/shipments/{id}',
        'label' => '/shipments/{id}/label'
    ];

    public const CARRIER_RESOURCES = [
      'authenticate' => '/authenticate'
    ];

    public const RATE_RESOURCES = [
      'rates' => '/rates'
    ];

    public const MANAGER_RESOURCES = [
        'iframe' => '/public/ui/?',
        'connect' => '/connect'
    ];

    public const TYPE_PICKUPPOINT = 'pickuppoint';

    public const TYPE_COURIER = 'courier';
}
