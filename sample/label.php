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
    'super-special-local-dev-shop-instance',
    $metaData
);

header('Content-Type: application/pdf');

echo $mcs->getLabel('unisend', $_GET["shipmentId"]);
