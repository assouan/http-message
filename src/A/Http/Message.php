<?php

declare(strict_types=1);

namespace A\Http;

class Message
{
    protected(set) Headers $headers;

    protected(set) string $version;

    public string $body;

    public int $content_length { get { return strlen($this->body); } }

    public function __construct(string $version = '1.1', Headers|array $headers = [], string $body = '')
    {
        $this->headers = $headers instanceof Headers ? $headers : new Headers($headers);
        $this->body = $body;
        $this->version = $version;
    }

    public function json(bool $assoc = true) : mixed
    {
        return json_decode($this->body, $assoc);
    }

    protected static function split_head(string $packet) : ?array
    {
        $split = static::split_packet($packet);

        if ($split === null)
        {
            return null;
        }

        return [$split[0], $split[1]];
    }

    protected static function split_packet(string $packet) : ?array
    {
        $position = strpos($packet, "\r\n\r\n");
        $length = 4;

        if ($position === false)
        {
            $position = strpos($packet, "\n\n");
            $length = 2;
        }

        if ($position === false)
        {
            return null;
        }

        return [substr($packet, 0, $position), substr($packet, $position + $length), $position + $length];
    }

    protected static function head_lines(string $head) : array
    {
        return preg_split("/\r\n|\n|\r/", $head) ?: [];
    }

    protected static function body_from_headers(Headers $headers, string $body, bool $wait_complete, bool $unknown_is_incomplete) : ?string
    {
        $packet = static::body_packet($headers, $body, $wait_complete, $unknown_is_incomplete);

        return $packet[0] ?? null;
    }

    protected static function body_packet(Headers $headers, string $body, bool $wait_complete, bool $unknown_is_incomplete) : ?array
    {
        if (str_contains(strtolower($headers->value('transfer-encoding', '') ?? ''), 'chunked'))
        {
            $packet = static::chunked_body($body);

            if ($packet === null and $wait_complete)
            {
                return null;
            }

            if ($packet === null)
            {
                throw new \RuntimeException('Incomplete HTTP chunked body.');
            }

            return $packet;
        }

        $content_length = $headers->value('content-length');

        if ($content_length !== null)
        {
            $length = (int)$content_length;

            if (strlen($body) < $length)
            {
                if ($wait_complete)
                {
                    return null;
                }

                throw new \RuntimeException('Incomplete HTTP body.');
            }

            return [substr($body, 0, $length), $length];
        }

        if ($wait_complete and $unknown_is_incomplete)
        {
            return null;
        }

        return [$body, strlen($body)];
    }

    protected static function chunked_body(string $body) : ?array
    {
        $decoded = '';
        $offset = 0;
        $length = strlen($body);

        while (true)
        {
            $line_end = strpos($body, "\r\n", $offset);

            if ($line_end === false)
            {
                return null;
            }

            $line = trim(substr($body, $offset, $line_end - $offset));
            $size = hexdec(explode(';', $line, 2)[0]);
            $offset = $line_end + 2;

            if ($size === 0)
            {
                $trailers_end = strpos($body, "\r\n\r\n", $offset);

                if ($trailers_end !== false)
                {
                    return [$decoded, $trailers_end + 4];
                }

                if (substr($body, $offset, 2) === "\r\n")
                {
                    return [$decoded, $offset + 2];
                }

                return null;
            }

            if ($length < $offset + $size + 2)
            {
                return null;
            }

            $decoded .= substr($body, $offset, $size);
            $offset += $size;

            if (substr($body, $offset, 2) !== "\r\n")
            {
                throw new \RuntimeException('Invalid HTTP chunk separator.');
            }

            $offset += 2;
        }
    }
}
