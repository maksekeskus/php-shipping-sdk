<?php

namespace MakeCommerceShipping\SDK\Http;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use MakeCommerceShipping\SDK\Environment;
use MakeCommerceShipping\SDK\Exception\MCException;

class MakeCommerceClient implements HttpClientInterface
{
    private Client $client;

    /**
     * @var string
     */
    private string $shippingUrl;

    /**
     * @var string
     */
    private string $managerUrl;

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
                $this->setShippingUrl(self::DEV_SHIPPING_URI);
                $this->setManagerUrl(self::DEV_MANAGER_URI);
                $this->setApiUrl(self::DEV_API_URI);
                break;
            case Environment::TEST:
                $this->setShippingUrl(self::TEST_SHIPPING_URI);
                $this->setManagerUrl(self::TEST_MANAGER_URI);
                $this->setApiUrl(self::TEST_API_URI);
                break;
            case Environment::LIVE:
                $this->setShippingUrl(self::LIVE_SHIPPING_URI);
                $this->setManagerUrl(self::LIVE_MANAGER_URI);
                $this->setApiUrl(self::LIVE_API_URI);
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
    public function setShippingUrl(string $url)
    {
        $this->shippingUrl = $url;
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
     * @param string $url
     * @return void
     */
    public function setApiUrl(string $url)
    {
        $this->apiUrl = $url;
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
    /**
     * @param string $method
     * @param string $endpoint
     * @param array $body
     * @param array $additionalHeaders
     * @param string $requestType
     * @return MCResponse
     * @throws GuzzleException
     * @throws MCException
     */
    protected function makeApiRequest(
        string $method,
        string $endpoint,
        array $body = [],
        array $additionalHeaders = [],
        string $requestType = self::REQUEST_TYPE_SHIPPING
    ): MCResponse {
        if (
            !in_array($requestType, [
            self::REQUEST_TYPE_MANAGER,
            self::REQUEST_TYPE_SHIPPING,
            self::REQUEST_TYPE_API])
        ) {
            throw new InvalidArgumentException('Unknown request type: ' . $requestType);
        }
        switch ($requestType) {
            case self::REQUEST_TYPE_API:
                $uri = $this->apiUrl;
                break;
            case self::REQUEST_TYPE_SHIPPING:
                $uri = $this->shippingUrl;
                break;
            case self::REQUEST_TYPE_MANAGER:
                $uri = $this->managerUrl;
                break;
        }
        $uri .= $endpoint;

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
    public function getRates(array $data, array &$location = []): object
    {
        if (isset($data['weight'])) {
            $data['weight'] = (int)round($data['weight']);
        }

        $response = $this->makeApiRequest(self::POST, self::RATE_RESOURCES['rates'], $data);

        $encodedLoc = $response->headers['MakeCommerce-Rates-Location'][0] ?? null;

        if ($encodedLoc && ($decoded = base64_decode($encodedLoc, true))) {
            $location = $decoded;
        }

        return $response->body;
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
     * @param string $carrier  Carrier slug, e.g. 'omniva', 'dpd', 'unisend'
     * @param array  $shipment Array of shipment objects. Each element: order{id,reference},
     *                         destination{id,country}, recipient{name,phone,email}
     * @param string $type     Use MakeCommerceClient::TYPE_PICKUPPOINT or TYPE_COURIER
     * @return array|mixed  Decoded body. Key field: trackingId — pass to getLabel(), getShipment(), updateShipment()
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
     * @param string $trackingId Short tracking code from createShipment() (e.g. "204138EE568")
     * @return array|mixed|object
     * @throws GuzzleException
     * @throws MCException
     */
    public function getShipment(
        string $trackingId
    ) {
        $endpoint = str_replace('{id}', $trackingId, self::SHIPMENT_RESOURCES['shipment']);

        return $this->makeApiRequest(self::GET, $endpoint)->body;
    }

    /**
     * @param string $carrier    Carrier slug, e.g. 'omniva', 'dpd', 'unisend'
     * @param array  $shipment   Same structure as createShipment() $shipment parameter
     * @param string $type       Use MakeCommerceClient::TYPE_PICKUPPOINT or TYPE_COURIER
     * @param string $trackingId Short tracking code from createShipment() (e.g. "204138EE568")
     * @return array|mixed|object
     * @throws GuzzleException
     * @throws MCException
     */
    public function updateShipment(
        string $carrier,
        array $shipment,
        string $type,
        string $trackingId
    ) {
        $this->validateShipmentType($type);

        $endpoint = str_replace('{id}', $trackingId, self::SHIPMENT_RESOURCES['shipment']);

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
     * @param string $trackingId Short tracking code from createShipment() (e.g. "204138EE568"), not the UUID
     * @param string $type
     * @return string
     * @throws GuzzleException
     * @throws MCException
     */
    public function getLabel(
        string $carrier,
        string $trackingId,
        string $type = self::TYPE_PICKUPPOINT
    ): string {
        $this->validateShipmentType($type);

        $endPoint = self::SHIPMENT_RESOURCES['label'];

        $endPoint = str_replace('{id}', $trackingId, $endPoint);

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
                "jwt" => $jwt,
                "locale" => $this->locale,
                "platform" => json_decode(base64_decode($this->appInfo), true)['platform'] ?? ''
            ]
        );

        return $this->managerUrl . self::MANAGER_RESOURCES['iframe'] . $queryString;
    }

    /**
     * Registers the shop with the platform and returns a short-lived JWT for getIframeUrl().
     * NOTE: This is the only method that returns MCResponse directly — access the JWT at $response->body->jwt.
     * All other methods return the decoded response body directly.
     *
     * @param string $userAgent  Value of $_SERVER['HTTP_USER_AGENT']
     * @param string $remoteAddr Protocol + domain hosting the iframe (e.g. 'https://myshop.com').
     *                           Must be HTTPS in production; HTTP only allowed for localhost.
     * @param string $orderUrl   Optional URL template for order detail pages, e.g. 'https://myshop.com/order/{id}/view'
     * @param string $webhookUrl Optional webhook URL for shipment status updates
     * @return MCResponse  Access JWT at $response->body->jwt and pass it to getIframeUrl()
     * @throws GuzzleException
     * @throws MCException
     */
    public function connectShop(
        string $userAgent,
        string $remoteAddr,
        string $orderUrl = '',
        string $webhookUrl = ''
    ): MCResponse {
        $body = [
            'shopId' => $this->shopId,
            'secretKey' => $this->secretKey,
            'instanceId' => $this->instanceId,
            'webhookUrl' => $webhookUrl,
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
            'MakeCommerce-Carrier-Credentials' => base64_encode(json_encode($credentials)),
            'MakeCommerce-Carrier' => $carrier
        ];

        $endpoint = self::CARRIER_RESOURCES['authenticate'];

        $response = $this->makeApiRequest(self::GET, $endpoint, [], $headers);

        return $response->code === 200 && $response->body == 'Valid';
    }

    /**
     * @param string $subscription
     * @return bool
     * @throws GuzzleException
     * @throws MCException
     */
    public function changeSubscriptionPlan(string $subscription): bool
    {
        $subscription = strtoupper($subscription);
        $endpoint = self::CONFIGURATION_RESOURCES['subscription'];
        $body = ['subscription' => $subscription];
        $response = $this->makeApiRequest(self::POST, $endpoint, $body, [], self::REQUEST_TYPE_API);

        return $response->code === 200 && $response->rawBody == 'Success';
    }


    /**
     * @return bool
     * @throws GuzzleException
     * @throws MCException
     */
    public function deactivateSubscriptionPlan(): bool
    {
        $endpoint = self::CONFIGURATION_RESOURCES['deactivateSubscription'];
        $response = $this->makeApiRequest(self::POST, $endpoint, [], [], self::REQUEST_TYPE_API);

        return $response->code === 200 && $response->rawBody == 'Success';
    }
}
