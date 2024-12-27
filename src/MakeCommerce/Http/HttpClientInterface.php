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

    public const PARCEL_MACHINE_RESOURCES = [
        'Carrier' => '/parcelmachines/{carrier}',
        'ListParcelmachines' => '/parcelmachines'
    ];

    public const COURIER_RESOURCES = [
        'Carrier' => '/couriers/{carrier}',
        'ListCouriers' => '/couriers'
    ];

    public const SHIPMENT_RESOURCES = [
        'Shipments' => '/shipments',
        'Shipment' => '/shipments/{id}',
        'Label' => '/shipments/{id}/label'
    ];

    public const CARRIER_RESOURCES = [
      'Authenticate' => '/authenticate/{carrier}'
    ];

    public const MANAGER_RESOURCES = [
        'VisualizeConfigPage' => '/public/ui/?',
        'Connect' => '/public/ui/connect'
    ];

    public const TYPE_PICKUPPOINT = 'pickuppoint';

    public const TYPE_COURIER = 'courier';
}
