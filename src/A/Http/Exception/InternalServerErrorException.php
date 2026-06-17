<?php

namespace A\Http\Exception;

class InternalServerErrorException extends HttpServerException
{
    public function __construct($message = "", $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(500, 'Internal Server Error', $message, $code, $previous);
    }
}
