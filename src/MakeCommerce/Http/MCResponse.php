<?php

namespace MakeCommerceShipping\SDK\Http;

use MakeCommerceShipping\SDK\Exception\MCException;
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
     * @var array|object
     */
    public $body;

    /**
     * @var array
     */
    public $headers;

    /**
     * Reading row body first, cause after first read stream get cleaned
     *
     * @param ResponseInterface $response
     * @throws MCException
     */
    public function __construct(ResponseInterface $response)
    {
        if (!in_array($response->getStatusCode(), [200, 201])) {
            throw new MCException($response->getReasonPhrase(), $response->getStatusCode());
        }

        $this->headers = $response->getHeaders();
        $this->code = $response->getStatusCode();
        $this->rawBody = $response->getBody()->getContents();
        $this->body = json_decode($this->rawBody);
    }
}
