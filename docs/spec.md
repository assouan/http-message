# A\Http Message API contract

Contrat minimal des messages HTTP.

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
        string $reason = '',
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

## JsonResponse

```php
class JsonResponse extends Response
{
    public mixed $data;

    public static function from_response(Response $response) : static;
}
```
