<?php

namespace A\Http\Exception;

class NotFoundException extends HttpClientException
{
    public function __construct($message = "", $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(404, 'Not Found', $message, $code, $previous);
    }
}
