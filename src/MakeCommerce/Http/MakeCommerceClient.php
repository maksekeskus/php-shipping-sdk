<?php

namespace MakeCommerceShipping\SDK\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MakeCommerceShipping\SDK\Environment;
use MakeCommerceShipping\SDK\Exception\MCException;

class MakeCommerceClient implements HttpClientInterface
{
    private Client $client;

    /**
     * @var string Base URL of the Shipping API — every endpoint lives here
     */
    private string $shippingUrl;

    /**
     * @var string Base URL of the shipping manager UI, used only by getIframeUrl()
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
     * @var string Base64-encoded app metadata, empty when none was supplied
     */
    private string $appInfo;

    /**
     * @var string
     */
    private string $locale = 'en';

    /**
     * @param string $environment Environment::TEST or Environment::LIVE
     * @param string $shopId
     * @param string $shopSecret
     * @param string $instanceId Identifies one shop installation; must stay the same across all requests
     * @param array $appInfo Optional platform/module metadata, e.g. ['platform' => 'Woocommerce']
     * @throws MCException
     */
    public function __construct(
        string $environment,
        string $shopId,
        string $shopSecret,
        string $instanceId,
        array $appInfo = []
    ) {
        switch ($environment) {
            case Environment::TEST:
                $this->shippingUrl = self::TEST_SHIPPING_URI;
                $this->managerUrl = self::TEST_MANAGER_URI;
                break;
            case Environment::LIVE:
                $this->shippingUrl = self::LIVE_SHIPPING_URI;
                $this->managerUrl = self::LIVE_MANAGER_URI;
                break;
            default:
                throw new MCException(
                    'Unknown environment: ' . $environment
                    . '. Use Environment::TEST or Environment::LIVE.',
                    400
                );
        }

        $this->shopId = $shopId;
        $this->secretKey = $shopSecret;
        $this->instanceId = $instanceId;
        $this->appInfo = $appInfo === [] ? '' : base64_encode((string) json_encode($appInfo));
        $this->client = new Client([
            'auth' => [$this->shopId, $this->secretKey],
            // Let error responses reach the SDK so the API's own message survives; without
            // this Guzzle throws before MCResponse is ever built.
            'http_errors' => false
        ]);
    }

    /**
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
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
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'makecommerce-shop-instance' => $this->instanceId,
            'makecommerce-user-locale' => $this->locale
        ];

        if ($this->appInfo !== '') {
            $headers['makecommerce-shipping-appinfo'] = $this->appInfo;
        }

        $requestContent = ['headers' => array_merge($headers, $additionalHeaders)];
        $uri = $this->shippingUrl . $endpoint;

        switch ($method) {
            case self::GET:
                $response = $this->client->get($uri, $requestContent);
                break;
            case self::POST:
                $requestContent['body'] = (string) json_encode($body);
                $response = $this->client->post($uri, $requestContent);
                break;
            case self::PUT:
                $requestContent['body'] = (string) json_encode($body);
                $response = $this->client->put($uri, $requestContent);
                break;
            default:
                throw new MCException('Unsupported HTTP method: ' . $method, 400);
        }

        $mcResponse = new MCResponse($response);

        if ($mcResponse->code < 200 || $mcResponse->code > 299) {
            throw new MCException(
                $mcResponse->message ?? $response->getReasonPhrase(),
                $mcResponse->code,
                null,
                null,
                $mcResponse
            );
        }

        return $mcResponse;
    }

    /**
     * Registers the shop with the platform and returns a short-lived JWT for getIframeUrl().
     * NOTE: This is the only method that returns MCResponse directly — access the JWT at $response->body->jwt.
     * All other methods return the decoded response body.
     *
     * @param string $remoteAddr The address permitted to embed the iframe, e.g. 'https://myshop.com'.
     *                           The platform resolves the host from this value, so a bare host
     *                           also works. Passed through as sent.
     * @param string|null $orderUrl Optional URL template for order pages, e.g. 'https://myshop.com/order/{id}/view'
     * @param string|null $webhookUrl Optional webhook URL for shipment status updates
     * @return MCResponse Access the JWT at $response->body->jwt and pass it to getIframeUrl()
     * @throws GuzzleException
     * @throws MCException
     */
    public function connectShop(
        string $remoteAddr,
        ?string $orderUrl = null,
        ?string $webhookUrl = null
    ): MCResponse {
        $body = ['REMOTE_ADDR' => $remoteAddr];

        if ($orderUrl !== null && $orderUrl !== '') {
            $body['orderUrl'] = $orderUrl;
        }

        if ($webhookUrl !== null && $webhookUrl !== '') {
            $body['webhookUrl'] = $webhookUrl;
        }

        return $this->makeApiRequest(self::POST, self::SHOP_RESOURCES['connect'], $body);
    }

    /**
     * Builds the shipping manager iframe URL. This is a local string build, not an API call.
     *
     * @param string $jwt JWT from connectShop()
     * @return string
     */
    public function getIframeUrl(string $jwt): string
    {
        $queryString = http_build_query(
            [
                'jwt' => $jwt,
                'locale' => $this->locale,
                'platform' => $this->platform()
            ]
        );

        return $this->managerUrl . self::MANAGER_RESOURCES['iframe'] . $queryString;
    }

    /**
     * @param array $data ['weight' => int (grams), 'destination' => '2-letter country code']
     * @return object {pickuppoint: Rate[], courier: Rate[]}, each Rate {carrier, title, price, image}
     * @throws GuzzleException
     * @throws MCException
     */
    public function getRates(array $data): object
    {
        if (isset($data['weight'])) {
            $data['weight'] = (int) round((float) $data['weight']);
        }

        return $this->decodeObject(
            $this->makeApiRequest(self::POST, self::RATE_RESOURCES['rates'], $data)
        );
    }

    /**
     * @param string $carrier Carrier slug, e.g. 'omniva', 'dpd', 'unisend'
     * @param string $country 2-letter ISO 3166-1 alpha-2 country code
     * @return array List of pickup points {name, city, id, address, zip, mc_address_zip, x, y}
     * @throws GuzzleException
     * @throws MCException
     */
    public function listCarrierDestinations(string $carrier, string $country): array
    {
        $endpoint = str_replace(
            '{country}',
            mb_strtolower($country),
            self::PICKUPPOINT_RESOURCES['listCarrierDestinations']
        );

        return $this->decodeArray(
            $this->makeApiRequest(self::GET, $endpoint, [], ['makecommerce-carrier' => $carrier])
        );
    }

    /**
     * @param string $carrier Carrier slug, e.g. 'omniva', 'dpd', 'unisend'
     * @param string $method MakeCommerceClient::TYPE_PICKUPPOINT or TYPE_COURIER
     * @param array $shipments List of shipments. Each element: order{id,reference},
     *                         destination (pickuppoint{id,country} or courier{zip,country,county,city,street}),
     *                         recipient{name,phone,email}
     * @return object {trackingId, trackingLink, shipment{...}} — pass trackingId to the other shipment methods
     * @throws GuzzleException
     * @throws MCException
     */
    public function createShipment(string $carrier, string $method, array $shipments): object
    {
        $this->validateMethod($method);

        return $this->decodeObject(
            $this->makeApiRequest(
                self::POST,
                self::SHIPMENT_RESOURCES['shipments'],
                $shipments,
                $this->carrierHeaders($carrier, $method)
            )
        );
    }

    /**
     * @param int|null $size Number of shipments per page
     * @param string|null $pageToken Token for the next page, from a previous response
     * @return object {items: Shipment[], nextPageToken, previousPageToken, count}
     * @throws GuzzleException
     * @throws MCException
     */
    public function getShipments(?int $size = null, ?string $pageToken = null): object
    {
        $endpoint = self::SHIPMENT_RESOURCES['shipments'];
        $query = [];

        if ($size !== null) {
            $query['size'] = $size;
        }

        if ($pageToken !== null && $pageToken !== '') {
            $query['pageToken'] = $pageToken;
        }

        if ($query !== []) {
            $endpoint .= '?' . http_build_query($query);
        }

        return $this->decodeObject($this->makeApiRequest(self::GET, $endpoint));
    }

    /**
     * @param string $trackingId Tracking code from createShipment() (e.g. "204138EE568"), not the UUID
     * @return object
     * @throws GuzzleException
     * @throws MCException
     */
    public function getShipment(string $trackingId): object
    {
        return $this->decodeObject(
            $this->makeApiRequest(self::GET, $this->shipmentEndpoint('shipment', $trackingId))
        );
    }

    /**
     * @param string $trackingId Tracking code from createShipment() (e.g. "204138EE568")
     * @param string $carrier Carrier slug, e.g. 'omniva', 'dpd', 'unisend'
     * @param string $method MakeCommerceClient::TYPE_PICKUPPOINT or TYPE_COURIER
     * @param array $shipments Same structure as the createShipment() $shipments parameter
     * @return object
     * @throws GuzzleException
     * @throws MCException
     */
    public function updateShipment(
        string $trackingId,
        string $carrier,
        string $method,
        array $shipments
    ): object {
        $this->validateMethod($method);

        return $this->decodeObject(
            $this->makeApiRequest(
                self::PUT,
                $this->shipmentEndpoint('shipment', $trackingId),
                $shipments,
                $this->carrierHeaders($carrier, $method)
            )
        );
    }

    /**
     * @param string $trackingId Tracking code from createShipment() (e.g. "204138EE568"), not the UUID
     * @return string Raw PDF bytes
     * @throws GuzzleException
     * @throws MCException
     */
    public function getLabel(string $trackingId): string
    {
        $response = $this->makeApiRequest(self::GET, $this->shipmentEndpoint('label', $trackingId));

        return (string) $response->rawBody;
    }

    /**
     * Verifies a shipment status webhook sent to the webhookUrl registered via connectShop().
     *
     * The notification arrives as a form-encoded POST with a `json` field carrying the status
     * update and a `mac` field signing it, where the MAC is
     * UPPERCASE(HEX(SHA-512(string(json) + string(secretKey)))).
     *
     * Always verify before acting on a notification, otherwise anyone who knows the webhook
     * URL can move order statuses:
     *
     *     if (!$mcs->validateWebhook($_POST)) {
     *         http_response_code(400);
     *         return;
     *     }
     *     $update = json_decode($_POST['json']);
     *
     * @param array $payload The received POST fields, expecting 'json' and 'mac'
     * @return bool True only when the signature matches
     */
    public function validateWebhook(array $payload): bool
    {
        if (!isset($payload['json'], $payload['mac'])) {
            return false;
        }

        if (!is_string($payload['json']) || !is_string($payload['mac'])) {
            return false;
        }

        $expected = strtoupper(hash('sha512', $payload['json'] . $this->secretKey));

        return hash_equals($expected, strtoupper($payload['mac']));
    }

    /**
     * @param string $carrier
     * @param string $method
     * @return array
     */
    private function carrierHeaders(string $carrier, string $method): array
    {
        return [
            'makecommerce-carrier' => $carrier,
            'makecommerce-method' => $method
        ];
    }

    /**
     * @param string $resource Key in SHIPMENT_RESOURCES
     * @param string $trackingId
     * @return string
     */
    private function shipmentEndpoint(string $resource, string $trackingId): string
    {
        return str_replace('{id}', rawurlencode($trackingId), self::SHIPMENT_RESOURCES[$resource]);
    }

    /**
     * @return string
     */
    private function platform(): string
    {
        if ($this->appInfo === '') {
            return '';
        }

        $decoded = json_decode((string) base64_decode($this->appInfo), true);

        return is_array($decoded) ? (string) ($decoded['platform'] ?? '') : '';
    }

    /**
     * @param string $method
     * @throws MCException
     */
    private function validateMethod(string $method): void
    {
        if (!in_array($method, [self::TYPE_PICKUPPOINT, self::TYPE_COURIER], true)) {
            throw new MCException(
                'Delivery method is invalid. Must be either: '
                . self::TYPE_PICKUPPOINT . ' or ' . self::TYPE_COURIER,
                400
            );
        }
    }

    /**
     * @param MCResponse $response
     * @return object
     * @throws MCException
     */
    private function decodeObject(MCResponse $response): object
    {
        if (!is_object($response->body)) {
            throw new MCException(
                'Expected a JSON object in the API response.',
                $response->code,
                null,
                null,
                $response
            );
        }

        return $response->body;
    }

    /**
     * @param MCResponse $response
     * @return array
     * @throws MCException
     */
    private function decodeArray(MCResponse $response): array
    {
        if (!is_array($response->body)) {
            throw new MCException(
                'Expected a JSON array in the API response.',
                $response->code,
                null,
                null,
                $response
            );
        }

        return $response->body;
    }
}
