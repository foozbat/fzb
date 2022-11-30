<?php
namespace Fzb;

use Iterator;
use IteratorIterator;
use ArrayAccess;

class RenderVar implements ArrayAccess, Iterator
{
    private $__data;
    public $unsafe;
    public $iterator;
    
    function __construct($data)
    {
        $this->__data = $data;
        $this->unsafe = &$this->__data;

        if (is_iterable($this->__data) && !is_array($this->__data)) {
            $this->iterator = new IteratorIterator($this->__data);
        }
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
        if (is_array($this->__data)) {
            return new RenderVar(current($this->__data));
        } else if (is_iterable($this->__data)) {
            return new RenderVar($this->iterator->current());
        } else {
            return null;
        }
    }

    public function key(): mixed
    {
        if (is_array($this->__data)) {
            return key($this->__data);
        } else if (is_iterable($this->__data)) {
            return $this->iterator->key();
        } else {
            return null;
        }
    }

    public function next(): void
    {
        if (is_array($this->__data)) {
            next($this->__data);
        } else if (is_iterable($this->__data)) {
            print("NEXTING");
            $this->iterator->next();
        }
    }

    public function rewind(): void
    {
        if (is_array($this->__data)) {
            reset($this->__data);
        } else if (is_iterable($this->__data)) {
            $this->iterator->rewind();
        }
    }

    public function valid (): bool
    {
        if (is_array($this->__data)) {
            return key($this->__data) !== null;
        } else if (is_iterable($this->__data)) {
            return $this->iterator->valid();
        } else {
            return false;
        }
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