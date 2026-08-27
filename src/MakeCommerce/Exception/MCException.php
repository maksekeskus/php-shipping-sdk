<?php

namespace MakeCommerceShipping\SDK\Exception;

use Exception;
use MakeCommerceShipping\SDK\Http\MCResponse;
use Throwable;

class MCException extends Exception
{
    /**
     * @var string Reserved for a future API-specific error code. Currently always null.
     */
    protected $mcErrorCode;

    /**
     * @var MCResponse|null The response that caused this exception, when there was one
     */
    protected $response;

    public function __construct(
        $message = '',
        $code = 0,
        ?Throwable $previous = null,
        $mcErrorCode = null,
        ?MCResponse $response = null
    ) {
        $this->mcErrorCode = $mcErrorCode;
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Reserved for a future API-specific error code; currently always null.
     *
     * @return mixed|string|null
     */
    public function getMcErrorCode()
    {
        return $this->mcErrorCode;
    }

    /**
     * The full API response behind this exception, exposing the status code, raw body,
     * headers and the decoded error body. Null for client-side validation failures.
     *
     * @return MCResponse|null
     */
    public function getResponse(): ?MCResponse
    {
        return $this->response;
    }
}
