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
    Environment::TEST,
    'f7741ab2-7445-45f9-9af4-0d0408ef1e4c',
    'pfOsGD9oPaFEILwqFLHEHkPf7vZz4j3t36nAcufP1abqT9l99koyuC1IWAOcBeqt',
    $metaData
);

$instanceId = '6729eeb1d5cf39.89737764';

echo "<html><head></head><body>";

foreach ($mcs->getParcelmachines() as $carrier) {
    echo "<img src='$carrier->logo' style='height: 24px;' title='$carrier->title' alt='$carrier->title' />";
    foreach ($carrier->destinations as $destination) {
        echo "<div>$destination->id: &euro;" . number_format((float)$destination->defaultPrice / 100, 2) . "</div>";

        $machines = $mcs->listCarrierDestinations($carrier->id, $destination->id);
        echo "<select>";
        foreach ($machines as $machine) {
            echo "<option value='$machine->id'>$machine->name</option>";
        }
        echo "</select>";
    }

    echo "<br><br>";
}

$shipments = [
    [
        'orderId' => '1',
        'destinationId' => '2521',
        'recipient' => [
            'name' => 'John Smith',
            'phone' => '+37256237323',
            'email' => 'john.smith@maksekeskus.ee'
        ]
    ],
    [
        'orderId' => '2',
        'destinationId' => '2527',
        'recipient' => [
            'name' => 'Jane Doe',
            'phone' => '+37256237323',
            'email' => 'jane.doe@maksekeskus.ee'
        ]
    ]
];

$sender = [
    "name" => "Maksekeskus AS",
    "phone" => "+37258875115",
    "email" => "modules@maksekeskus.ee",
    "zip" => "10145",
    "city" => "Tallinn",
    "address" => "Liivalaia 45"
];

echo "<br><br>";

try {
    $mcs->connectShop($instanceId);
    $mcs->visualizeConfigPage($instanceId);
} catch (Exception $e) {
    echo $e->getMessage();
}

try {
    foreach ($mcs->createShipment('venipak', $shipments, $instanceId) as $shipment) {
        echo $shipment->shipmentId . " <a target='_blank' href='" . $shipment->trackingLink . "'>track</a> 
     <a target='_blank' href='/label.php?shipmentId=" . $shipment->shipmentId . "'>label</a><br>";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}

echo "</body></html>";
