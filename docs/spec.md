# A\Http API contract

Contrat minimal des messages HTTP, client et connexions protocole.

## Header

```php
class Header implements ArrayAccess, Countable, IteratorAggregate, Stringable
{
    protected(set) string $name;
    protected(set) array $values;

    public string $key;
    public ?string $first;
    public string $value;
    public string $line;

    public function __construct(string $name, string|array|null $values = null);
    public function set(string|array|null $values) : static;
    public function add(string|array|null $values) : static;
    public function append_to_last(string $value) : static;
    public function __toString() : string;
}
```

## Headers

```php
class Headers implements ArrayAccess, Countable, IteratorAggregate, Stringable
{
    public function __construct(Headers|array $headers = []);

    public static function parse(array|string $lines) : static;

    public function set(Header|string $name, string|array|null $values = null) : static;
    public function add(string $name, string|array|null $values) : static;
    public function has(string $name) : bool;
    public function value(string $name, ?string $default = null) : ?string;
    public function remove(string $name) : static;
    public function lines() : array;
    public function to_array() : array;
    public function __toString() : string;
}
```

## Message

```php
class Message
{
    protected(set) Headers $headers;
    protected(set) string $version;
    public string $body;
    public int $content_length;

    public function __construct(string $version = '1.1', Headers|array $headers = [], string $body = '');
    public function json(bool $assoc = true) : mixed;
}
```

## Request

```php
class Request extends Message
{
    protected(set) string $method;
    protected(set) string $url;

    public string $scheme;
    public string $host;
    public int $port;

    public function __construct(
        string $method = 'GET',
        string $url = '/',
        string $version = '1.1',
        Headers|array $headers = [],
        string $body = '',
    );

    public static function authority_for(string $host, int $port) : string;
    public static function parse(string $packet) : static;
    public static function try_parse(string $packet) : ?static;

    public function target(bool $absolute = false) : string;
    public function to_packet(bool $absolute = false) : string;
    public function __toString() : string;
}
```

## Response

```php
class Response extends Message
{
    protected(set) int $status;
    protected(set) string $reason;
    public bool $ok;

    public function __construct(
        string $version = '1.1',
        int $status = 200,
        string $reason = 'OK',
        Headers|array $headers = [],
        string $body = '',
    );

    public static function parse(string $packet, bool $body_allowed = true) : static;
    public static function parse_packet(string $packet, bool $body_allowed = true) : array;
    public static function try_parse(string $packet, bool $body_allowed = true) : ?static;
    public static function try_parse_packet(string $packet, bool $body_allowed = true) : ?array;

    public function to_packet() : string;
    public function __toString() : string;
}
```

## Proxy

```php
class Proxy
{
    protected(set) string $type;
    protected(set) string $host;
    protected(set) int $port;
    protected(set) ?string $username;
    protected(set) ?string $password;
    protected(set) bool $verify_peer;
    protected(set) bool $verify_peer_name;
    protected(set) bool $allow_self_signed;

    public static function http(
        string $host,
        int $port = 8080,
        ?string $username = null,
        ?string $password = null,
        bool $verify_peer = true,
        bool $verify_peer_name = true,
        bool $allow_self_signed = false,
    ) : static;

    public static function socks4(string $host, int $port = 1080, ?string $username = null) : static;
    public static function socks5(string $host, int $port = 1080, ?string $username = null, ?string $password = null) : static;

    public function socket_for(Request $request, string $protocol) : TcpSocket;
}
```

## HttpClient

```php
class HttpClient
{
    protected(set) Headers $headers;

    public function __construct(
        Headers|array $headers = [],
        ?callable $connection_factory = null,
        ?Proxy $proxy = null,
    );

    public function get(string $url, Headers|array $headers = [], string $version = '1.1') : Promise;
    public function post(string $url, Headers|array $headers = [], string $body = '', string $version = '1.1') : Promise;
    public function request(string $method, string $url, Headers|array $headers = [], string $body = '', string $version = '1.1') : Promise;
    public function send(Request $request) : Promise; // Response
    public function close() : void;
}
```

## CurlClient

```php
class CurlClient
{
    protected(set) Headers $headers;
    protected(set) array $options;

    public function __construct(array $options = [], ?ProxyConfig $proxy_config = null, Headers|array $headers = []);
    public function set_option(int $option, mixed $value) : static;
    public function set_options(array $options) : static;

    public function get(string $url, Headers|array $headers = [], string $version = '1.1') : Promise|Response;
    public function post(string $url, Headers|array $headers = [], string $body = '', string $version = '1.1') : Promise|Response;
    public function request(string $method, string $url, Headers|array $headers = [], string $body = '', string $version = '1.1') : Promise|Response;
    public function send_request(Request $request) : Promise|Response;

    public function get_json(string $url, Headers|array $headers = [], string $version = '1.1') : Promise|JsonResponse;
    public function post_json(string $url, mixed $json = null, Headers|array $headers = [], string $version = '1.1') : Promise|JsonResponse;
    public function request_json(string $method, string $url, mixed $json = null, Headers|array $headers = [], string $version = '1.1') : Promise|JsonResponse;

    public function close() : void;
}
```

`CurlClient` utilise `curl_multi_*`, pas les sockets `A\Network`.

## JsonResponse

```php
class JsonResponse extends Response
{
    public mixed $data;

    public static function from_response(Response $response) : static;
}
```

## Protocol connections

```php
namespace A\Http\Protocol\Http1;

class Connection
{
    protected(set) TcpSocket $socket;
    protected(set) string $host;
    protected(set) int $port;
    protected(set) bool $closed;

    public bool $connected;
    public bool $available;

    public static function for_request(Request $request, ?Proxy $proxy = null) : static;
    public function __construct(?TcpSocket $socket = null);
    public function send(Request $request) : Promise; // HttpExchange
    public function connect(string $host, int $port) : bool;
    public function close() : void;
}
```

```php
namespace A\Http\Protocol\Http2;

class Connection
{
    protected(set) TcpSocket $socket;
    protected(set) string $host;
    protected(set) int $port;
    protected(set) bool $closed;

    public bool $connected;
    public bool $available;

    public static function for_request(Request $request, ?Proxy $proxy = null) : static;
    public function __construct(?TcpSocket $socket = null);
    public function send(Request $request) : Promise; // HttpExchange
    public function connect(string $host, int $port) : bool;
    public function close() : void;
}
```

## HTTP/2 helpers

```php
class Frame
{
    public const DATA = 0;
    public const HEADERS = 1;
    public const RST_STREAM = 3;
    public const SETTINGS = 4;
    public const GOAWAY = 7;
    public const WINDOW_UPDATE = 8;

    public const END_STREAM = 0x01;
    public const ACK = 0x01;
    public const END_HEADERS = 0x04;

    protected(set) int $type;
    protected(set) int $flags;
    protected(set) int $stream_id;
    protected(set) string $payload;

    public function __construct(int $type, int $flags, int $stream_id, string $payload = '');
    public static function encode(int $type, int $flags, int $stream_id, string $payload = '') : string;
    public static function try_decode(string $buffer) : ?array;
}

class Hpack
{
    public function encode(array $headers) : string;
    public function decode(string $block) : array;
}
```
