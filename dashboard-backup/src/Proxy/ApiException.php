<?php

namespace Proxy;

class ApiException extends \ErrorException
{
    const DEFAULT_ERROR_CODE = '500';

    /** @var string */
    protected $errorCode;

    public function __construct($message, $code = self::DEFAULT_ERROR_CODE)
    {
        parent::__construct($message, 0, 1);

        $this->errorCode = $code;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }
}
