<?php
/**
 * Class Fzb\RenderVar
 * 
 * Container for a render variable which returns HTML-safe output for any printable type.
 * Typically only used internally by Fzb\Renderer
 * 
 * @author  Aaron Bishop (github.com/foozbat)
 * 
 * @todo Possibly rename this class, do more testing.
 */

namespace Fzb;

use Iterator;
use IteratorIterator;
use ArrayAccess;

class RenderVar implements ArrayAccess, Iterator
{
    private $__data;
    public $unsafe;
    public $iterator;
    
    /**
     * Constructor
     *
     * @param mixed $data Data to be rendered
     */
    function __construct(mixed $data)
    {
        $this->__data = $data;
        $this->unsafe = &$this->__data;

        if (is_iterable($this->__data) && !is_array($this->__data)) {
            $this->iterator = new IteratorIterator($this->__data);
        }
    }

    /**
     * Returns a RenderVar encapsulated public member variable for a class
     *
     * @param string $name Member Variable
     * @return RenderVar
     */
    public function __get(string $name): RenderVar
    {
        if (property_exists($this->__data, $name)) {
            return new RenderVar($this->__data->{$name});
        }
    }

    /**
     * HTML-safe output gette
     *
     * @return string
     */
    public function __toString()
    {
        if (is_object($this->__data)) {
            return _htmlspecialchars( (string) $this->__data );
        }
        
        return _htmlspecialchars( $this->__data );
    }

    /**
     * Calls class method and gets HTML-safe output
     *
     * @param string $method class method to call
     * @param array $args Method arguments
     * @return mixed HTML-safe output or RenderVar
     */
    public function __call(string $method, array $args): mixed
    {
        if (method_exists($this->__data, $method)) {
            $ret = $this->__data->$method(...$args);
            if ($ret !== null) {
                return _htmlspecialchars($ret);
            }
        }
    }

    /**
     * Array Access Methods
     */
    
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

    /**
     * Iterator Methods
     * 
     * Supports iterating an an internal array or iterable using IteratorIterator
     */

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

/**
 * Wrapper for htmlspecialchars to support arrays and objects
 *
 * @param mixed $data Data to be rendered HTML-safe
 * @return void HTML-safe output or RenderVar
 */
function _htmlspecialchars(mixed $data)
{
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