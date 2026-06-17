<?php

declare(strict_types=1);

namespace A\Http;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Stringable;
use Traversable;

class Headers implements ArrayAccess, Countable, IteratorAggregate, Stringable
{
    protected array $headers = [];

    public function __construct(Headers|array $headers = [])
    {
        foreach ($headers as $name => $value)
        {
            if ($value instanceof Header)
            {
                $this->set($value);
                continue;
            }

            $this->set((string)$name, $value);
        }
    }

    public static function parse(array|string $lines) : static
    {
        if (is_string($lines))
        {
            $lines = preg_split("/\r\n|\n|\r/", $lines) ?: [];
        }

        $headers = new static();
        $current = null;

        foreach ($lines as $line)
        {
            if ($line === '')
            {
                continue;
            }

            if (($line[0] === ' ' or $line[0] === "\t") and $current !== null)
            {
                $headers[$current]->append_to_last($line);
                continue;
            }

            if (!str_contains($line, ':'))
            {
                throw new \InvalidArgumentException("Invalid HTTP header line: {$line}");
            }

            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $current = $name;
            $headers->add($name, trim($value));
        }

        return $headers;
    }

    public function set(Header|string $name, string|array|null $values = null) : static
    {
        $header = $name instanceof Header ? new Header($name->name, $name->values) : new Header($this->format_name($name), $values);
        $this->headers[$header->key] = $header;

        return $this;
    }

    public function add(string $name, string|array|null $values) : static
    {
        $this[$name]->add($values);

        return $this;
    }

    public function has(string $name) : bool
    {
        return array_key_exists($this->key($name), $this->headers);
    }

    public function value(string $name, ?string $default = null) : ?string
    {
        return $this->headers[$this->key($name)]->value ?? $default;
    }

    public function remove(string $name) : static
    {
        unset($this->headers[$this->key($name)]);

        return $this;
    }

    public function lines() : array
    {
        return array_map(static fn (Header $header) : string => $header->line, $this->headers);
    }

    public function to_array() : array
    {
        $headers = [];

        foreach ($this->headers as $header)
        {
            $headers[$header->name] = $header->values;
        }

        return $headers;
    }

    public function __toString() : string
    {
        return implode("\r\n", $this->lines());
    }

    public function getIterator() : Traversable
    {
        $headers = [];

        foreach ($this->headers as $header)
        {
            $headers[$header->name] = $header;
        }

        return new ArrayIterator($headers);
    }

    public function count() : int
    {
        return count($this->headers);
    }

    public function offsetExists(mixed $offset) : bool
    {
        return $this->has((string)$offset);
    }

    public function offsetGet(mixed $offset) : Header
    {
        $key = $this->key((string)$offset);

        if (!isset($this->headers[$key]))
        {
            $this->headers[$key] = new Header($this->format_name((string)$offset));
        }

        return $this->headers[$key];
    }

    public function offsetSet(mixed $offset, mixed $value) : void
    {
        if ($value instanceof Header)
        {
            $this->set($value);

            return;
        }

        if ($offset === null)
        {
            throw new \InvalidArgumentException('A header name is required.');
        }

        $this->set((string)$offset, is_array($value) ? $value : (string)$value);
    }

    public function offsetUnset(mixed $offset) : void
    {
        $this->remove((string)$offset);
    }

    protected function key(string $name) : string
    {
        return strtolower(trim($name));
    }

    protected function format_name(string $name) : string
    {
        return implode('-', array_map(
            static fn (string $part) : string => ucfirst(strtolower($part)),
            explode('-', trim($name)),
        ));
    }
}
