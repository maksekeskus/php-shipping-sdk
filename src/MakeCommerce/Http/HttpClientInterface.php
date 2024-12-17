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
        'CreateShipment' => '/parcelmachines/{carrier}/shipments',
        'GetShipmentLabel' => '/parcelmachines/{carrier}/shipments/{shipment}/label',
        'ListCarrierDestinations' => '/parcelmachines/{carrier}/destinations/{country}',
        'ListDestinations' => '/parcelmachines/{carrier}/destinations',
        'ListParcelmachines' => '/parcelmachines'
    ];

    public const COURIER_RESOURCES = [
        'Carrier' => '/couriers/{carrier}',
        'CreateShipment' => '/couriers/{carrier}/shipments',
        'GetShipmentLabel' => '/couriers/{carrier}/shipments/{shipment}/label',
        'ListDestinations' => '/couriers/{carrier}/destinations',
        'ListCouriers' => '/couriers'
    ];

    public const SHIPMENT_RESOURCES = [
        'Shipments' => '/shipments/',
        'Shipment' => '/shipments/{id}'
    ];

    public const CARRIER_RESOURCES = [
      'Authenticate' => '/authenticate/{carrier}'
    ];

    public const MANAGER_RESOURCES = [
        'VisualizeConfigPage' => '/public/ui/?',
        'Connect' => '/public/ui/connect'
    ];

    public const TYPE_PARCEL = 'parcelmachine';

    public const TYPE_COURIER = 'courier';
}
