<?php

namespace MakeCommerceShipping\SDK\Http;

interface HttpClientInterface
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const DEV_BASE_URI = 'https://shipping.dev.makecommerce.net';
    public const TEST_BASE_URI = 'https://shipping.test.makecommerce.net';
    public const LIVE_BASE_URI = 'https://shipping.makecommerce.net';
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

    public const TYPE_PARCEL = 'parcel';

    public const TYPE_COURIER = 'courier';
}
