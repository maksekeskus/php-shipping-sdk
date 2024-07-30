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
     * @var string Shop ID
     */
    private string $shopId;

    /**
     * @var string Secret Key
     */
    private string $secretKey;

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
    public function __construct(string $environment, string $shopId, string $shopSecret, array $appInfo)
    {
        switch ($environment) {
            case Environment::DEV:
                $this->apiUrl = self::DEV_BASE_URI;
                break;
            case Environment::TEST:
                $this->apiUrl = self::TEST_BASE_URI;
                break;
            case Environment::LIVE:
                $this->apiUrl = self::LIVE_BASE_URI;
                break;
        }

        $this->shopId = $shopId;
        $this->secretKey = $shopSecret;
        $this->appInfo = base64_encode(json_encode($appInfo));
        $this->client = new Client(['auth' => [$this->shopId, $this->secretKey]]);
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
     * @param array $sender
     * @param string $originCountry
     * @param array $credentials
     * @return array|mixed
     * @throws MCException|GuzzleException
     */
    public function createShipment(
        string $carrier,
        array $data,
        array $sender,
        string $originCountry,
        array $credentials = [],
        string $type = self::TYPE_PARCEL
    ) {
        $this->validateShipmentType($type);

        if ($type === self::TYPE_COURIER && empty($credentials)) {
            throw new MCException(
                'Credentials must be included!',
                400
            );
        }

        if ($type === self::TYPE_PARCEL) {
            $endPoint = str_replace('{carrier}', $carrier, self::PARCEL_MACHINE_RESOURCES['CreateShipment']);
        } else {
            $endPoint = str_replace('{carrier}', $carrier, self::COURIER_RESOURCES['CreateShipment']);
        }

        $headers = [
            'MakeCommerce-Shipping-Sender' => base64_encode(json_encode($sender)),
            'MakeCommerce-Shipping-OriginCountry' => $originCountry
        ];

        if ($credentials) {
            $headers['MakeCommerce-Carrier-Credentials'] = base64_encode(json_encode($credentials));
        }

        return $this->makeApiRequest(self::POST, $endPoint, $data, $headers)->body;
    }

    /**
     * @param string $carrier
     * @param string $shipmentId
     * @return string
     * @throws MCException|GuzzleException
     */
    public function getLabel(
        string $carrier,
        string $shipmentId,
        array $credentials = [],
        string $type = self::TYPE_PARCEL
    ) {
        $this->validateShipmentType($type);

        if ($type === self::TYPE_COURIER && empty($credentials)) {
            throw new MCException(
                'Credentials must be included!',
                400
            );
        }

        $additionalHeaders = [];
        if ($credentials) {
            $additionalHeaders['MakeCommerce-Carrier-Credentials'] = base64_encode(json_encode($credentials));
        }

        if ($type === self::TYPE_PARCEL) {
            $endPoint = str_replace('{carrier}', $carrier, self::PARCEL_MACHINE_RESOURCES['GetShipmentLabel']);
        } else {
            $endPoint = str_replace('{carrier}', $carrier, self::COURIER_RESOURCES['GetShipmentLabel']);
        }
        $endPoint = str_replace('{shipment}', $shipmentId, $endPoint);

        return $this->makeApiRequest(self::GET, $endPoint, [], $additionalHeaders)->rawBody;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $body
     * @param array $additionalHeaders
     * @return MCResponse
     * @throws GuzzleException
     * @throws MCException
     */
    protected function makeApiRequest(
        string $method,
        string $endpoint,
        array $body = [],
        array $additionalHeaders = []
    ): MCResponse {
        $uri = $this->apiUrl . $endpoint;

        $headers = [
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'MakeCommerce-Shipping-ShopId' => $this->shopId,
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
