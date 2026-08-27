<?php

namespace MakeCommerceShipping\SDK\Http;

interface HttpClientInterface
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const TEST_SHIPPING_URI = 'https://shipping.test.makecommerce.net';
    public const LIVE_SHIPPING_URI = 'https://shipping.makecommerce.net';
    public const TEST_MANAGER_URI = 'https://shipping-manager.test.makecommerce.net';
    public const LIVE_MANAGER_URI = 'https://shipping-manager.makecommerce.net';

    public const SHOP_RESOURCES = [
        'connect' => '/connect'
    ];

    public const RATE_RESOURCES = [
        'rates' => '/rates'
    ];

    public const PICKUPPOINT_RESOURCES = [
        'listCarrierDestinations' => '/pickuppoint/{country}'
    ];

    public const SHIPMENT_RESOURCES = [
        'shipments' => '/shipments',
        'shipment' => '/shipments/{id}',
        'label' => '/shipments/{id}/label'
    ];

    public const MANAGER_RESOURCES = [
        'iframe' => '/public/ui/?'
    ];

    public const TYPE_PICKUPPOINT = 'pickuppoint';

    public const TYPE_COURIER = 'courier';
}
