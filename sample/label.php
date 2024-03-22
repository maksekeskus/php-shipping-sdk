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

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="venipak-'.$_GET["shipmentId"].'-label.pdf"');

echo $mcs->getLabel('venipak', $_GET["shipmentId"]);