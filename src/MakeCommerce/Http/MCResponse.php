<?php

namespace MakeCommerceShipping\SDK\Http;

use Psr\Http\Message\ResponseInterface;

class MCResponse
{
    /**
     * @var int
     */
    public $code;

    /**
     * @var mixed
     */
    public $rawBody;

    /**
     * @var array|object|null Decoded body, or null when the response is not JSON
     */
    public $body;

    /**
     * @var array
     */
    public $headers;

    /**
     * @var string|null The API's error message, when the response carries one
     */
    public $message;

    /**
     * Reading raw body first, cause after first read stream get cleaned.
     *
     * This is a plain value object: it never throws. Mapping a non-2xx status onto an
     * MCException is MakeCommerceClient::makeApiRequest()'s job.
     *
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->headers = $response->getHeaders();
        $this->code = $response->getStatusCode();
        $this->rawBody = $response->getBody()->getContents();
        $this->body = null;
        $this->message = null;

        // Only decode when the API says it is JSON, so binary payloads such as the
        // label PDF are never run through json_decode().
        if (stripos($response->getHeaderLine('content-type'), 'json') !== false) {
            $decoded = json_decode((string) $this->rawBody);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = $decoded;
            }
        }

        if (is_object($this->body) && isset($this->body->message) && is_string($this->body->message)) {
            $this->message = $this->body->message;
        }
    }
}
