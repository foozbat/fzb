<?php
namespace Fzb;

use Iterator;
use ArrayAccess;

class RenderVar implements ArrayAccess, Iterator
{
    private $__data;
    public $unsafe;
    
    function __construct($data)
    {
        $this->__data = $data;
        $this->unsafe = &$this->__data;
    }

    public function unsafe(): mixed
    {
        return $this->__data;
    }

    public function __get(string $name) {
        if (property_exists($this->__data, $name)) {
            return new RenderVar($this->__data->{$name});
        }
    }

    public function __toString() {
        if (is_object($this->__data)) {
            return _htmlspecialchars( (string) $this->__data );
        } else {
            return _htmlspecialchars( $this->__data );
        }
    }

    public function __call($method, $args) {
        if (method_exists($this->__data, $method)) {
            $ret = $this->__data->$method(...$args);
            if ($ret !== null) {
                return _htmlspecialchars($ret);
            }
        }
    }

    // ArrayAccess methods
    public function offsetSet($input_name, $properties = null): void
    {
        return;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->__data[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->__data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->__data[$offset]) ? new RenderVar($this->__data[$offset]) : null;
    }  

    // Iterator methods
    public function current(): mixed
    {
        return new RenderVar(current($this->__data));
    }

    public function key(): mixed
    {
        return key($this->__data);
    }

    public function next(): void
    {
        next($this->__data);
    }

    public function rewind(): void
    {
        reset($this->__data);
    }

    public function valid (): bool
    {
        return key($this->__data) !== null;
    }
}

function _htmlspecialchars($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value ) {
            $data[htmlspecialchars($key)] = _htmlspecialchars($value);
        }
    } else if (is_object($data)) {
        $data = new RenderVar($data);
    } else if ($data !== null) {
        $data = htmlspecialchars($data);
    }
    return $data;
}