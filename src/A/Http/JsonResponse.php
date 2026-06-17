<?php

declare(strict_types=1);

namespace A\Http;

class JsonResponse extends Response
{
    public mixed $data { get { return $this->json(); } }

    public static function from_response(Response $response) : static
    {
        return new static($response->version, $response->status, $response->reason, $response->headers, $response->body);
    }
}
