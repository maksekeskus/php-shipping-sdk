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

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="unisend-'.$_GET["shipmentId"].'-label.pdf"');

echo $mcs->getLabel('unisend', $_GET["shipmentId"]);
