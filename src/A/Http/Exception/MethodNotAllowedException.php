<?php

namespace A\Http\Exception;

class MethodNotAllowedException extends HttpClientException
{
    public function __construct($message = "", $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(405, 'Method Not Allowed', $message, $code, $previous);
    }
}
