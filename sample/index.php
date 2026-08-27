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
    '3425d8b7-0225-4367-8c6f-16b1aba8d766',
    'J5S4lcVjC1QfJec8IQPhHSKeAiEf10bPV7KrHPx9AmIl9nCoEtNtJo63SF0YKpFQ',
    'sdk-sample',
    $metaData
);

echo "<pre>";

// RemoteAddr identifies the site allowed to embed the iframe. The platform resolves the
// host from this value, so a bare $_SERVER['HTTP_HOST'] works too — a full URL is just
// clearer, and is what the API reference shows.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$remoteAddress = $scheme . '://' . $_SERVER['HTTP_HOST'];
$orderUrl = $remoteAddress . '/order/{id}/view';

// Needed to complete setup
$token = $mcs->connectShop($remoteAddress, $orderUrl);
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
    MakeCommerceClient::TYPE_PICKUPPOINT,
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
    ]
);
echo '<br>
Shipment pickuppoint: ' . print_r($shipment, true);

echo '<br>
Label: <a target="_blank" href="/label.php?shipmentId='.$shipment->trackingId.'">'.$shipment->trackingId.'</a>
';

echo "</pre>";
