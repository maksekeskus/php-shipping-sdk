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
     * @var string $locale
     */
    private string $locale = 'en';

    /**
     * @param string $environment
     * @param string $shopId
     * @param string $shopSecret
     * @param string $instanceId
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
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return array
     * @throws Exception
     * @throws GuzzleException|MCException
     */
    //TODO How will this change with the flattening
    public function getPickuppoints(): array
    {
        return $this->makeApiRequest(self::GET, self::PICKUPPOINT_RESOURCES['listPickupPoints'])->body;
    }

    /**
     * @return array
     * @throws Exception
     * @throws GuzzleException|MCException
     */
    //TODO How will this change with the flattening
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
            'MakeCommerce-Shipping-AppInfo' => $this->appInfo,
            'MakeCommerce-User-Locale' => $this->locale
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
     * @return array|mixed|object
     * @throws GuzzleException
     * @throws MCException
     */
    //TODO How will this change with the flattening

    public function getCouriers()
    {
        return $this->makeApiRequest(self::GET, self::COURIER_RESOURCES['listCouriers'])->body;
    }

    /**
     * @param string $carrier
     * @param string $country
     * @return array
     * @throws GuzzleException|MCException
     */
    //TODO How will this change with the flattening
    /**
     * @param string $type
     * @throws MCException
     */
    private function validateShipmentType(string $type): void
    {
        if (!in_array($type, [self::TYPE_PICKUPPOINT, self::TYPE_COURIER])) {
            throw new MCException(
                'Shipment type is invalid. Must be either: ' . self::TYPE_PICKUPPOINT . ' or ' . self::TYPE_COURIER,
                400
            );
        }
    }

    /**
     * @throws MCException
     * @throws GuzzleException
     */
    public function getRates(array $data, string $locale = 'en'): object
    {
        $additionalHeaders = [
            'MakeCommerce-User-Locale' => $locale
        ];
        return $this->makeApiRequest(self::POST, self::RATE_RESOURCES['rates'], $data, $additionalHeaders)->body;
    }

    public function listCarrierDestinations(string $carrier, string $country): array
    {
        $endPoint = str_replace(
            '{country}',
            mb_strtolower($country),
            self::PICKUPPOINT_RESOURCES['listCarrierDestinations']
        );


        return $this->makeApiRequest(self::GET, $endPoint, [], ['MakeCommerce-Carrier' => $carrier])->body;
    }

    /**
     * @param string $carrier
     * @param array $shipment
     * @param string $type
     * @return array|mixed
     * @throws GuzzleException
     * @throws MCException
     */
    public function createShipment(
        string $carrier,
        array $shipment,
        string $type
    ) {
        $this->validateShipmentType($type);

        return $this->makeApiRequest(
            self::POST,
            self::SHIPMENT_RESOURCES['shipments'],
            $shipment,
            [
                'MakeCommerce-Carrier' => $carrier,
                'MakeCommerce-Method' => $type
            ]
        )->body;
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
        $endpoint = self::SHIPMENT_RESOURCES['shipments'];

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
        $endpoint = str_replace('{id}', $shipmentId, self::SHIPMENT_RESOURCES['shipment']);

        return $this->makeApiRequest(self::GET, $endpoint)->body;
    }

    /**
     * @param string $carrier
     * @param array $shipment
     * @param string $type
     * @param string $shipmentId
     *
     * @return array|mixed|object
     * @throws GuzzleException
     * @throws MCException
     */
    public function updateShipment(
        string $carrier,
        array $shipment,
        string $type,
        string $shipmentId
    ) {
        $this->validateShipmentType($type);

        $endpoint = str_replace('{id}', $shipmentId, self::SHIPMENT_RESOURCES['shipment']);

        return $this->makeApiRequest(
            self::PUT,
            $endpoint,
            $shipment,
            [
                'MakeCommerce-Carrier' => $carrier,
                'MakeCommerce-Method' => $type
            ]
        )->body;
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
        string $type = self::TYPE_PICKUPPOINT
    ): string {
        //TODO Headers for carrier and type?
        $this->validateShipmentType($type);

        $endPoint = self::SHIPMENT_RESOURCES['label'];

        $endPoint = str_replace('{id}', $shipmentId, $endPoint);

        return $this->makeApiRequest(self::GET, $endPoint)->rawBody;
    }


    /**
     * @param string $jwt
     * @return string
     */
    public function getIframeUrl(string $jwt): string
    {

        $queryString = http_build_query(
            [
                "jwt" => $jwt
            ]
        );

        return $this->managerUrl . self::MANAGER_RESOURCES['iframe'] . $queryString;
    }

    /**
     * @param string $userAgent
     * @param string $remoteAddr
     * @param string $orderUrl
     * @return MCResponse
     * @throws GuzzleException
     * @throws MCException
     */
    public function connectShop(string $userAgent, string $remoteAddr, string $orderUrl = ''): MCResponse
    {
        $body = [
            'shopId' => $this->shopId,
            'secretKey' => $this->secretKey,
            'instanceId' => $this->instanceId,
            'orderUrl' => $orderUrl,
            'HTTP_USER_AGENT' => $userAgent,
            'REMOTE_ADDR' => $remoteAddr
        ];


        $endpoint = self::MANAGER_RESOURCES['connect'];

        return $this->makeApiRequest(self::POST, $endpoint, $body, []);
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
    ): bool {
        $headers = [
            'MakeCommerce-Carrier-Credentials' => base64_encode(json_encode($credentials))
        ];

        $endpoint = str_replace('{carrier}', $carrier, self::CARRIER_RESOURCES['authenticate']);

        $response = $this->makeApiRequest(self::GET, $endpoint, [], $headers);

        return $response->code === 200;
    }
}
