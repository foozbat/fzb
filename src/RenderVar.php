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
use ArrayIterator;
use EmptyIterator;

class RenderVar implements ArrayAccess, Iterator
{
    public readonly mixed $unsafe;
    private ArrayIterator|IteratorIterator|EmptyIterator $iterator;
    
    /**
     * Constructor
     *
     * @param mixed $data Data to be rendered
     */
    function __construct(mixed $data)
    {
        $this->unsafe = $data;

        if (is_array($this->unsafe)) {
            $this->iterator = new ArrayIterator($this->unsafe);
        } else if (is_iterable($this->unsafe)) {
            $this->iterator = new IteratorIterator($this->unsafe);
        } else {
            $this->iterator = new EmptyIterator();
        }
    }

    /**
     * Returns a output for a stored object
     *
     * @param string $name Member Variable
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this->unsafe, $name)) {
            if (is_string($this->unsafe->{$name}) || is_object($this->unsafe->{$name})) {
                return new RenderVar($this->unsafe->{$name});
            } 
            
            return $this->unsafe->{$name};
        }
        return null;
    }

    /**
     * HTML-safe output gette
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->unsafe instanceof \Stringable) {
            return _htmlspecialchars( (string) $this->unsafe );
        }
        
        return _htmlspecialchars( $this->unsafe );
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
        if (method_exists($this->unsafe, $method)) {
            $ret = $this->unsafe->$method(...$args);
            if ($ret !== null) {
                if (is_string($this->unsafe->{$name}) || is_object($this->unsafe->{$name})) {
                    return new RenderVar($ret);
                } else {
                    return _htmlspecialchars($ret);
                }
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
        return isset($this->unsafe[$offset]);
    }

    public function offsetUnset($offset): void
    {
        return;
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->unsafe[$offset]) ? new RenderVar($this->unsafe[$offset]) : null;
    }  

    /**
     * Iterator Methods
     * 
     * Supports iterating an an internal array or iterable
     */

    public function current(): mixed
    {
        return new RenderVar($this->iterator->current());
    }

    public function key(): mixed
    {
        return $this->iterator->key();
    }

    public function next(): void
    {
        $this->iterator->next();
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
    }

    public function valid (): bool
    {
        return $this->iterator->valid();
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
    } else if (is_string($data)) {
        $data = htmlspecialchars($data);
    }
    return $data;
}