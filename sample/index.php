<?php

require '../vendor/autoload.php';

use MakeCommerceShipping\SDK\Environment;
use MakeCommerceShipping\SDK\Http\MakeCommerceClient;

$metaData = [
    "module" => "MakeCommerce",
    "module_version" => "4.0.5",
    "platform" => "Woocommerce",
    "platform_version" => "10.2.2"
];

$mcs = new MakeCommerceClient(
    Environment::TEST,
    'YOUR_SHOP_ID',
    'YOUR_SECRET_ID',
    'sdk-sample',
    $metaData
);

echo "<pre>";

// RemoteAddr is the parent page hosting the iframe (protocol + domain required).
// Must use HTTPS for production domains (e.g., https://my-example-domain.com).
// HTTP is ONLY permitted for localhost (e.g., http://localhost:8080).
$remoteAddress = $_SERVER['HTTP_HOST'];
$orderUrl = $remoteAddress . 'order/{id}/view';

// Needed to complete setup
$token = $mcs->connectShop(
    $_SERVER['HTTP_USER_AGENT'],
    $remoteAddress,
    $orderUrl
);
$url = $mcs->getIframeUrl($token->body->jwt);
echo '<iframe id="mcIframe" src="' . $url . '" width="100%" height="720px"></iframe>';

//rates
echo 'Rates: ' . print_r($mcs->getRates([
    'weight' => '2500',
    'destination' => 'EE'
]), true);


//machine list
$machines = $mcs->listCarrierDestinations('unisend', 'EE');
echo '<br>
PickupPoints: ' . print_r($machines, true);

//shipment
$shipment = $mcs->createShipment(
    'unisend',
    [
        [
            'order' => [
                'id' => '1',
                'reference' => 'Example-Order-Reference'
            ],
            'destination' => [
                'id' => '9002',
                'country' => 'EE'
            ],
            'recipient' => [
                'name' => 'John Smith',
                'phone' => '+37256123123',
                'email' => 'john.smith@maksekeskus.ee'
            ]
        ]
    ],
    'pickuppoint'
);
echo '<br>
Shipment pickuppoint: ' . print_r($shipment, true);

echo '<br>
Label: <a target="_blank" href="/label.php?shipmentId='.$shipment->trackingId.'">'.$shipment->trackingId.'</a>
';

echo "</pre>";
