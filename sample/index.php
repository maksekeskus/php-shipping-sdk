<?php

require '../vendor/autoload.php';

use MakeCommerceShipping\SDK\Environment;
use MakeCommerceShipping\SDK\Http\MakeCommerceClient;

$metaData = [
    "module" => "WooCommerce",
    "module_version" => "3.2",
    "platform" => "Wordpress",
    "platform_version" => "5.1"
];

$mcs = new MakeCommerceClient(
    Environment::DEV,
    '213d0d1d-ff95-46ef-adcb-db466abc462c',
    'QWEhT30Lx73t2yi8zWKqC0kvlUIIJAkwHU0fIrEsi1Ofc8XlzjgEcIO8VHrss2gs',
    'super-special-local-dev-shop-instance',
    $metaData
);

echo "<pre>";



//rates
echo 'Rates: ' . print_r($mcs->getRates([
    'weight' => '2500',
    'destination' => 'EE'
]), true);


//pickuppoint carriers
echo '<br>
PickupPoint carriers: ' . print_r($mcs->getPickuppoints(), true);

//courier carriers
echo '<br>
Courier carriers: ' . print_r($mcs->getCouriers(), true);

//machine list
$machines = $mcs->listCarrierDestinations('unisend', 'EE');
echo '<br>
PickupPoints: ' . print_r($machines, true);

//shipment
$shipment = $mcs->createShipment(
    'unisend',
    [
        [
            'orderId' => '1',
            'destination' => [
                'id' => '9002',
                'country' => 'EE'
            ],
            'recipient' => [
                'name' => 'John Smith',
                'phone' => '+37256237323',
                'email' => 'john.smith@maksekeskus.ee'
            ]
        ]
    ],
    'pickuppoint'
);
echo '<br>
Shipment pickuppoint: ' . print_r($shipment, true);

echo '<br>
Label: <a target="_blank" href="/label.php?shipmentId='.$shipment->trackingLink.'">'.$shipment->trackingLink.'</a>
';

try {
    $mcs->connectShop();
    $mcs->visualizeConfigPage();
} catch (Exception $e) {
    echo $e->getMessage();
}

echo "</pre>";
