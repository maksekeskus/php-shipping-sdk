<?php

namespace MakeCommerceShipping\SDK\Exception;

use Exception;
use Throwable;

class MCException extends Exception
{
    /**
     * @var string
     */
    protected $mcErrorCode;

    public function __construct(
        $message = '',
        $code = 0,
        Throwable $previous = null,
        $mcErrorCode = null
    ) {
        $this->mcErrorCode = $mcErrorCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return mixed|string|null
     */
    public function getMcErrorCode()
    {
        return $this->mcErrorCode;
    }
}
