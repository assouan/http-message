<?php

declare(strict_types=1);

namespace A\Http;

class Request extends Message
{
    protected(set) string $method;

    protected(set) string $url;

    public array $attributes = [];

    public string $path { get { return parse_url($this->url, PHP_URL_PATH) ?: '/'; } }

    public array $query
    {
        get
        {
            parse_str((string)(parse_url($this->url, PHP_URL_QUERY) ?? ''), $query);

            return $query;
        }
    }

    public string $scheme { get { return strtolower((string)(parse_url($this->url, PHP_URL_SCHEME) ?: 'http')); } }

    public string $authority
    {
        get
        {
            if (preg_match('/^\[[^\]]+\]:\d+$/', $this->url))
            {
                return $this->url;
            }

            $host = $this->host;

            if ($host === '')
            {
                return $this->headers->value('host', '') ?? '';
            }

            return static::authority_for($host, $this->port);
        }
    }

    public string $host
    {
        get
        {
            if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $this->url, $match))
            {
                return $match[1];
            }

            $host = parse_url($this->url, PHP_URL_HOST);

            if (is_string($host) and $host !== '')
            {
                return $host;
            }

            $host = $this->headers->value('host', '') ?? '';

            if (str_starts_with($host, '[') and ($end = strpos($host, ']')) !== false)
            {
                return substr($host, 1, $end - 1);
            }

            if (substr_count($host, ':') === 1)
            {
                return explode(':', $host, 2)[0];
            }

            return $host;
        }
    }

    public int $port
    {
        get
        {
            if (preg_match('/^\[[^\]]+\]:(\d+)$/', $this->url, $match))
            {
                return (int)$match[1];
            }

            $port = parse_url($this->url, PHP_URL_PORT);

            if (is_int($port))
            {
                return $port;
            }

            $host = $this->headers->value('host', '') ?? '';

            if (preg_match('/^\[[^\]]+\]:(\d+)$/', $host, $match))
            {
                return (int)$match[1];
            }

            if (substr_count($host, ':') === 1 and preg_match('/:(\d+)$/', $host, $match))
            {
                return (int)$match[1];
            }

            return $this->scheme === 'https' ? 443 : 80;
        }
    }

    public function __construct(
        string $method = 'GET',
        string $url = '/',
        string $version = '1.1',
        Headers|array $headers = [],
        string $body = '',
        array $attributes = [],
    ) {
        parent::__construct($version, $headers, $body);

        $this->method = strtoupper($method);
        $this->url = $url === '' ? '/' : $url;
        $this->attributes = $attributes;
    }

    public static function from_globals() : static
    {
        $headers = [];

        foreach ($_SERVER as $key => $value)
        {
            if (str_starts_with($key, 'HTTP_'))
            {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string)$value;
            }
        }

        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $name)
        {
            if (isset($_SERVER[$key]))
            {
                $headers[$name] = (string)$_SERVER[$key];
            }
        }

        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';

        return new static(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            str_starts_with($protocol, 'HTTP/') ? substr($protocol, 5) : $protocol,
            $headers,
            (string)file_get_contents('php://input'),
        );
    }

    public function with(string $name, mixed $value) : static
    {
        $request = clone $this;
        $request->attributes[$name] = $value;

        return $request;
    }

    public function attribute(string $name, mixed $default = null) : mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public static function authority_for(string $host, int $port) : string
    {
        if (str_contains($host, ':') and !str_starts_with($host, '['))
        {
            return "[{$host}]:{$port}";
        }

        return "{$host}:{$port}";
    }

    public static function parse(string $packet) : static
    {
        $split = static::split_head($packet);

        if ($split === null)
        {
            throw new \InvalidArgumentException('Invalid HTTP request packet.');
        }

        [$request_line, $headers, $body] = static::parse_start($split[0], $split[1]);
        $body = static::body_from_headers($headers, $body, false, false);

        return new static($request_line[1], $request_line[2], $request_line[3], $headers, $body ?? '');
    }

    public static function try_parse(string $packet) : ?static
    {
        $split = static::split_head($packet);

        if ($split === null)
        {
            return null;
        }

        [$request_line, $headers, $body] = static::parse_start($split[0], $split[1]);
        $body = static::body_from_headers($headers, $body, true, false);

        if ($body === null)
        {
            return null;
        }

        return new static($request_line[1], $request_line[2], $request_line[3], $headers, $body);
    }

    public function target(bool $absolute = false) : string
    {
        if ($this->method === 'CONNECT')
        {
            return $this->authority;
        }

        if ($absolute)
        {
            return $this->url;
        }

        $path = parse_url($this->url, PHP_URL_PATH) ?: '/';
        $query = parse_url($this->url, PHP_URL_QUERY);

        return $query === null ? $path : "{$path}?{$query}";
    }

    public function to_packet(bool $absolute = false) : string
    {
        $headers = (string)$this->headers;
        $head = "{$this->method} {$this->target($absolute)} HTTP/{$this->version}\r\n";

        if ($headers !== '')
        {
            $head .= "{$headers}\r\n";
        }

        return "{$head}\r\n{$this->body}";
    }

    public function __toString() : string
    {
        return $this->to_packet();
    }

    protected static function parse_start(string $head, string $body) : array
    {
        $lines = static::head_lines($head);
        $line = array_shift($lines);

        if (!is_string($line) or !preg_match('/^([A-Z]+)\s+(\S+)\s+HTTP\/(\d+(?:\.\d+)?)$/i', $line, $match))
        {
            throw new \InvalidArgumentException("Invalid HTTP request line: {$line}");
        }

        return [$match, Headers::parse($lines), $body];
    }
}
