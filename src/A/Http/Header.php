<?php

declare(strict_types=1);

namespace A\Http;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Stringable;
use Traversable;

class Header implements ArrayAccess, Countable, IteratorAggregate, Stringable
{
    protected const UNSPLITTABLE = [
        'date' => true,
        'expires' => true,
        'last-modified' => true,
        'set-cookie' => true,
    ];

    protected(set) string $name;

    protected(set) array $values = [];

    public string $key { get { return strtolower($this->name); } }

    public ?string $first { get { return $this->values[0] ?? null; } }

    public string $value { get { return implode(', ', $this->values); } }

    public string $line { get { return "{$this->name}: {$this->value}"; } }

    public function __construct(string $name, string|array|null $values = null)
    {
        $this->name = $name;
        $this->add($values);
    }

    public function set(string|array|null $values) : static
    {
        $this->values = [];

        return $this->add($values);
    }

    public function add(string|array|null $values) : static
    {
        if ($values === null)
        {
            return $this;
        }

        if (is_array($values))
        {
            foreach ($values as $value)
            {
                $this->add($value);
            }

            return $this;
        }

        foreach ($this->split($values) as $value)
        {
            $this->values[] = $value;
        }

        return $this;
    }

    public function append_to_last(string $value) : static
    {
        if (!$this->values)
        {
            $this->values[] = trim($value);

            return $this;
        }

        $key = array_key_last($this->values);
        $this->values[$key] .= ' ' . trim($value);

        return $this;
    }

    public function __toString() : string
    {
        return $this->value;
    }

    public function getIterator() : Traversable
    {
        return new ArrayIterator($this->values);
    }

    public function count() : int
    {
        return count($this->values);
    }

    public function offsetExists(mixed $offset) : bool
    {
        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset) : mixed
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value) : void
    {
        if ($offset === null)
        {
            $this->add((string)$value);

            return;
        }

        $this->values[$offset] = trim((string)$value);
    }

    public function offsetUnset(mixed $offset) : void
    {
        unset($this->values[$offset]);
        $this->values = array_values($this->values);
    }

    protected function split(string $value) : array
    {
        if (isset(static::UNSPLITTABLE[$this->key]))
        {
            return [trim($value)];
        }

        $values = [];
        $current = '';
        $quoted = false;
        $escaped = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++)
        {
            $char = $value[$i];

            if ($escaped)
            {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($quoted and $char === '\\')
            {
                $current .= $char;
                $escaped = true;
                continue;
            }

            if ($char === '"')
            {
                $quoted = !$quoted;
                $current .= $char;
                continue;
            }

            if (!$quoted and $char === ',')
            {
                $values[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $values[] = trim($current);

        return $values;
    }
}
