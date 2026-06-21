<?php

declare(strict_types=1);

namespace A\Http;

class Response extends Message
{
    protected(set) int $status;

    protected(set) string $reason;

    public bool $ok { get { return $this->status >= 200 && $this->status < 300; } }

    public function __construct(
        string $version = '1.1',
        int $status = 200,
        string $reason = '',
        Headers|array $headers = [],
        string $body = '',
    ) {
        parent::__construct($version, $headers, $body);

        $this->status = $status;
        $this->reason = $reason;
    }

    public static function parse(string $packet, bool $body_allowed = true) : static
    {
        [$response] = static::parse_packet($packet, $body_allowed);

        return $response;
    }

    public static function parse_packet(string $packet, bool $body_allowed = true) : array
    {
        $split = static::split_packet($packet);

        if ($split === null)
        {
            throw new \InvalidArgumentException('Invalid HTTP response packet.');
        }

        [$status_line, $headers, $body] = static::parse_start($split[0], $split[1]);
        $status = (int)$status_line[2];
        $body = $body_allowed && static::status_allows_body($status)
            ? static::body_packet($headers, $body, false, false)
            : ['', 0];

        return [new static($status_line[1], $status, $status_line[3] ?? '', $headers, $body[0] ?? ''), substr($packet, $split[2] + ($body[1] ?? 0))];
    }

    public static function try_parse(string $packet, bool $body_allowed = true) : ?static
    {
        $packet = static::try_parse_packet($packet, $body_allowed);

        return $packet[0] ?? null;
    }

    public static function redirect(string $location, int $status = 302) : static
    {
        return new static(status: $status, headers: ['Location' => $location]);
    }

    public static function json(mixed $data, int $status = 200) : static
    {
        return new static(
            status: $status,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            body: json_encode($data, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public static function try_parse_packet(string $packet, bool $body_allowed = true) : ?array
    {
        $split = static::split_packet($packet);

        if ($split === null)
        {
            return null;
        }

        [$status_line, $headers, $body] = static::parse_start($split[0], $split[1]);
        $status = (int)$status_line[2];
        $body = $body_allowed && static::status_allows_body($status)
            ? static::body_packet($headers, $body, true, true)
            : ['', 0];

        if ($body === null)
        {
            return null;
        }

        return [new static($status_line[1], $status, $status_line[3] ?? '', $headers, $body[0]), substr($packet, $split[2] + $body[1])];
    }

    public function to_packet() : string
    {
        $headers = (string)$this->headers;
        $head = "HTTP/{$this->version} {$this->status} {$this->reason}\r\n";

        if ($headers !== '')
        {
            $head .= "{$headers}\r\n";
        }

        return "{$head}\r\n{$this->body}";
    }

    public function __toString() : string
    {
        return $this->body;
    }

    protected static function parse_start(string $head, string $body) : array
    {
        $lines = static::head_lines($head);
        $line = array_shift($lines);

        if (!is_string($line) or !preg_match('/^HTTP\/(\d+(?:\.\d+)?)\s+(\d{3})(?:\s+(.*))?$/', $line, $match))
        {
            throw new \InvalidArgumentException("Invalid HTTP response line: {$line}");
        }

        return [$match, Headers::parse($lines), $body];
    }

    protected static function status_allows_body(int $status) : bool
    {
        return !($status >= 100 && $status < 200) && $status !== 204 && $status !== 304;
    }
}
