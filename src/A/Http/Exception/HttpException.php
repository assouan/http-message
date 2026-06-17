<?php

namespace A\Http\Exception;

class HttpException extends \RuntimeException
{
    public function __construct(protected int $statusCode, protected string $reasonPhrase, $message = "", $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }
}
