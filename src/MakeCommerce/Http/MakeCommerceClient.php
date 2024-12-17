<?php

namespace MakeCommerceShipping\SDK\Http;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MakeCommerceShipping\SDK\Environment;
use MakeCommerceShipping\SDK\Exception\MCException;

class MakeCommerceClient implements HttpClientInterface
{
    private Client $client;

    /**
     * @var string
     */
    private string $apiUrl;

    /**
     * @var string
     */
    private string $managerUrl;

    /**
     * @var string Shop ID
     */
    private string $shopId;

    /**
     * @var string Secret Key
     */
    private string $secretKey;

    /**
     * @var string Instance ID
     */
    private string $instanceId;

    /**
     * @var string $appInfo
     */
    private string $appInfo;

    /**
     * @param string $environment
     * @param string $shopId
     * @param string $shopSecret
     * @param array $appInfo
     */
    public function __construct(
        string $environment,
        string $shopId,
        string $shopSecret,
        string $instanceId,
        array $appInfo
    ) {
        switch ($environment) {
            case Environment::DEV:
                $this->setApiUrl(self::DEV_BASE_URI);
                $this->setManagerUrl(self::DEV_MANAGER_URI);
                break;
            case Environment::TEST:
                $this->setApiUrl(self::TEST_BASE_URI);
                $this->setManagerUrl(self::TEST_MANAGER_URI);
                break;
            case Environment::LIVE:
                $this->setApiUrl(self::LIVE_BASE_URI);
                $this->setManagerUrl(self::LIVE_MANAGER_URI);
                break;
        }

        $this->shopId = $shopId;
        $this->secretKey = $shopSecret;
        $this->instanceId = $instanceId;
        $this->appInfo = base64_encode(json_encode($appInfo));
        $this->client = new Client(['auth' => [$this->shopId, $this->secretKey]]);
    }

    /**
     * @param string $url
     * @return void
     */
    public function setApiUrl(string $url)
    {
        $this->apiUrl = $url;
    }

    /**
     * @param string $url
     * @return void
     */
    public function setManagerUrl(string $url)
    {
        $this->managerUrl = $url;
    }

    /**
     * @return array
     * @throws Exception
     * @throws GuzzleException|MCException
     */
    public function getParcelmachines(): array
    {
        return $this->makeApiRequest(self::GET, self::PARCEL_MACHINE_RESOURCES['ListParcelmachines'])->body;
    }

    /**
     * @return array
     * @throws Exception
     * @throws GuzzleException|MCException
     */
    public function getCouriers(): array
    {
        return $this->makeApiRequest(self::GET, self::COURIER_RESOURCES['ListCouriers'])->body;
    }

    /**
     * @param string $carrier
     * @return object
     * @throws GuzzleException|MCException
     */
    public function getCarrier(string $carrier, string $type = self::TYPE_PARCEL)
    {
        $this->validateShipmentType($type);

        if ($type === self::TYPE_PARCEL) {
            $endPoint = str_replace('{carrier}', $carrier, self::PARCEL_MACHINE_RESOURCES['Carrier']);
        } else {
            $endPoint = str_replace('{carrier}', $carrier, self::COURIER_RESOURCES['Carrier']);
        }

        return $this->makeApiRequest(self::GET, $endPoint)->body;
    }

    /**
     * @param string $carrier
     * @return array
     * @throws GuzzleException|MCException
     */
    public function listDestinations(string $carrier, string $type = self::TYPE_PARCEL)
    {
        $this->validateShipmentType($type);

        if ($type === self::TYPE_PARCEL) {
            $endPoint = str_replace('{carrier}', $carrier, self::PARCEL_MACHINE_RESOURCES['ListDestinations']);
        } else {
            $endPoint = str_replace('{carrier}', $carrier, self::COURIER_RESOURCES['ListDestinations']);
        }

        return $this->makeApiRequest(self::GET, $endPoint)->body;
    }

    /**
     * @param string $carrier
     * @param string $country
     * @return array
     * @throws GuzzleException|MCException
     */
    public function listCarrierDestinations(string $carrier, string $country)
    {
        $endPoint = str_replace('{carrier}', $carrier, self::PARCEL_MACHINE_RESOURCES['ListCarrierDestinations']);
        $endPoint = str_replace('{country}', mb_strtolower($country), $endPoint);

        return $this->makeApiRequest(self::GET, $endPoint)->body;
    }

    /**
     * @param string $carrier
     * @param array $data
     * @param string $type
     * @return array|mixed
     * @throws GuzzleException
     * @throws MCException
     */
    public function createShipment(
        string $carrier,
        array $data,
        string $type = self::TYPE_PARCEL
    ) {
        $this->validateShipmentType($type);

        if ($type === self::TYPE_PARCEL) {
            $endPoint = str_replace('{carrier}', $carrier, self::PARCEL_MACHINE_RESOURCES['CreateShipment']);
        } else {
            $endPoint = str_replace('{carrier}', $carrier, self::COURIER_RESOURCES['CreateShipment']);
        }

        return $this->makeApiRequest(self::POST, $endPoint, $data)->body;
    }

    /**
     * @param string $size
     * @param string $pageToken
     *
     * @return array|mixed|object
     * @throws GuzzleException
     * @throws MCException
     */
    public function getShipments(
        string $size = '',
        string $pageToken = ''
    ) {
        $endpoint = self::SHIPMENT_RESOURCES['Shipments'];

        $queryString = http_build_query(
            [
                "size" => $size,
                "pageToken" => $pageToken
            ]
        );

        $endpoint .= '?' . $queryString;

        return $this->makeApiRequest(self::GET, $endpoint)->body;
    }

    /**
     * @param string $shipmentId
     *
     * @return array|mixed|object
     * @throws GuzzleException
     * @throws MCException
     */
    public function getShipment(
        string $shipmentId
    ) {
        $endpoint = str_replace('{id}', $shipmentId, self::SHIPMENT_RESOURCES['Shipment']);

        return $this->makeApiRequest(self::GET, $endpoint)->body;
    }

    /**
     * @param array $data
     * @param string $shipmentId
     *
     * @return array|mixed|object
     * @throws GuzzleException
     * @throws MCException
     */
    public function updateShipment(
        array $data,
        string $shipmentId
    ) {
        $endpoint = str_replace('{id}', $shipmentId, self::SHIPMENT_RESOURCES['Shipment']);

        return $this->makeApiRequest(self::PUT, $endpoint, $data, $headers)->body;
    }

    /**
     * @param string $carrier
     * @param string $shipmentId
     * @param string $type
     * @return string
     * @throws GuzzleException
     * @throws MCException
     */
    public function getLabel(
        string $carrier,
        string $shipmentId,
        string $type = self::TYPE_PARCEL
    ): string {
        $this->validateShipmentType($type);

        if ($type === self::TYPE_COURIER) {
            throw new MCException(
                'Credentials must be included!',
                400
            );
        }

        if ($type === self::TYPE_PARCEL) {
            $endPoint = str_replace('{carrier}', $carrier, self::PARCEL_MACHINE_RESOURCES['GetShipmentLabel']);
        } else {
            $endPoint = str_replace('{carrier}', $carrier, self::COURIER_RESOURCES['GetShipmentLabel']);
        }
        $endPoint = str_replace('{shipment}', $shipmentId, $endPoint);

        return $this->makeApiRequest(self::GET, $endPoint, [])->rawBody;
    }

    /**
     * @param string $width
     * @param string $height
     * @return void
     */
    public function visualizeConfigPage(
        string $width = '100%',
        string $height = '1000px'
    ) {
        $payload = json_encode([
            'shopId' => $this->shopId,
            'instanceId' => $this->instanceId
        ]);

        $token = hash_hmac('sha256', $payload, $this->secretKey);

        $queryString = http_build_query(
            [
                "token" => $token,
                "shopId" => $this->shopId,
                "instanceId" => $this->instanceId
            ]
        );

        $iframeUrl = $this->managerUrl . self::MANAGER_RESOURCES['VisualizeConfigPage'] . $queryString;

        echo '<iframe src="' . $iframeUrl . '" height="' . $height . '" width="' . $width . '"></iframe>';
    }

    /**
     * @return MCResponse
     * @throws GuzzleException
     * @throws MCException
     */
    public function connectShop()
    {
        $body = [
            'shopId' => $this->shopId,
            'secretKey' => $this->secretKey,
            'instanceId' => $this->instanceId
        ];

        $endpoint = self::MANAGER_RESOURCES['Connect'];

        return $this->makeApiRequest(self::POST, $endpoint, $body, [], true);
    }

    /**
     * @param string $carrier
     * @param array $credentials
     *
     * @return bool
     * @throws GuzzleException
     * @throws MCException
     */
    public function validateCarrierCredentials(
        string $carrier,
        array $credentials
    ) {
        $headers = [
            'MakeCommerce-Carrier-Credentials' => base64_encode(json_encode($credentials))
        ];

        $endpoint = str_replace('{carrier}', $carrier, self::CARRIER_RESOURCES['Authenticate']);

        $response = $this->makeApiRequest(self::GET, $endpoint, [], $headers);

        return $response->code === 200;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $body
     * @param array $additionalHeaders
     * @param bool $managerRequest
     * @return MCResponse
     * @throws GuzzleException
     * @throws MCException
     */
    protected function makeApiRequest(
        string $method,
        string $endpoint,
        array $body = [],
        array $additionalHeaders = [],
        bool $managerRequest = false
    ): MCResponse {
        $uri = $this->apiUrl . $endpoint;
        if ($managerRequest) {
            $uri = $this->managerUrl . $endpoint;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'MakeCommerce-Shop' => $this->shopId,
            'MakeCommerce-Shop-Instance' => $this->instanceId,
            'MakeCommerce-Shipping-AppInfo' => $this->appInfo
        ];

        if (!empty($additionalHeaders)) {
            $headers = array_merge($headers, $additionalHeaders);
        }

        $requestContent = ['headers' => $headers];

        switch ($method) {
            case self::GET:
                $response = $this->client->get($uri, $requestContent);
                break;
            case self::POST:
                $requestContent['body'] = json_encode($body);
                $response = $this->client->post($uri, $requestContent);
                break;
            case self::PUT:
                $requestContent['body'] = json_encode($body);
                $response = $this->client->put($uri, $requestContent);
                break;
            default:
                throw new MCException('Request type should be defined!', 400);
        }

        return new MCResponse($response);
    }

    /**
     * @param string $type
     * @throws MCException
     */
    private function validateShipmentType(string $type): void
    {
        if (!in_array($type, [self::TYPE_PARCEL, self::TYPE_COURIER])) {
            throw new MCException(
                'Shipment type is invalid. Must be either: ' . self::TYPE_PARCEL . ' or ' . self::TYPE_COURIER,
                400
            );
        }
    }
}
