# MakeCommerce Shipping SDK 2.0

## Set up:

```e
Please see samples in "sample" directory
```

```php
<?php

require '../vendor/autoload.php';

use MakeCommerceShipping\SDK\Environment;
use MakeCommerceShipping\SDK\Http\MakeCommerceClient;

$metaData = [
    "module" => "WooCommerce",
    "module_version" => "3.2",
    "platform" => "Wordpress",
    "platform_version" => "5.1"
]; // Obligatory platform Meta data

$mcs = new MakeCommerceClient(
    Environment::TEST,
    'f7741ab2-7445-45f9-9af4-0d0408ef1e4c', // shopId
    'pfOsGD9oPaFEILwqFLHEHkPf7vZz4j3t36nAcufP1abqT9l99koyuC1IWAOcBeqt', //secretKey
    $metaData
);
```

To get your API keys, please visit merchant.test.maksekeskus.ee or merchant.maksekeskus.ee

``
live
``
Set true when using LIVE store credentials, sends requests to live endpoint

``
shopId
``
Retrieved from merchant.test.maksekeskus.ee or merchant.maksekeskus.ee

``
secretKey
``
Retrieved from merchant.test.maksekeskus.ee or merchant.maksekeskus.ee


## How to use:

Copy the contents of the repository to your desired location (preferably in the same folder where the implementation is located)

Following line:

```php
require __DIR__ . '/vendor/autoload.php';
```

needs to be added to the file which is implements shipments logic or some bootstrap file

Main SDK logic is located in following namespace

```php
use MakeCommerceShipping\SDK\Http\MakeCommerceClient;
```

# Example

``` php
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

$parcelMachines = $mcs->getParcelmachines();
?>
```



To run the application in development, you can run these commands 

```bash
composer start
```

Or you can use `docker-compose` to run the app with `docker`, so you can run these commands:
```bash
cd [my-app-name]
docker-compose up -d
```
After that, open `http://localhost:8080` in your browser.