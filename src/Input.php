<?php
/**
 * Class Input
 * 
 * This class contains provides an interface to safely handle inputs from get/post/path with validation and santization.
 * Analogous to request object in other frameworks, but with a focus on inputs
 * 
 * usage: Instantiate with $input = new Input();
 *        Constructor is passed a list of inputs to define, along with validation requirements
 *        Inputs can be accessed by referencing the object as an array, i.e. $input['myinput']
 * 
 * @todo refactor entire class.  I am not happy with it's implementation.
 */

namespace Fzb;

use ArrayAccess;
use Iterator;
use Exception;

class InputException extends Exception { }

class Input implements ArrayAccess, Iterator
{
    private $inputs = array();

    /**
     * Constructor
     *
     * @param mixed ...$inputs optionally receive an array of input definitions
     */
    public function __construct(mixed ...$inputs)
    {
        // check to see if this is a websocket connection
        if (strpos($_SERVER['GATEWAY_INTERFACE'], 'websocketd-CGI') !== false) {
            GLOBAL $_GET;
            $_GET = array();
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }

        $this->read_all_inputs($inputs);
    }
  
    private function read_all_inputs($inputs): void
    {
        if (isset($inputs)) {
            if (is_array($inputs)) {
                foreach ($inputs as $name => $properties) {
                    if (is_int($name)) {
                        $name = $properties;
                        $properties = null;
                    }
                    //$this->offsetSet($name, $properties);
                    $this->read_input($name, $properties);
                }
            }
        }
    }

    // TODO: remove or refactor
    public function get_validation_failures(): array
    {
/*        $validation_failures = array();
        $required_failures = array();

        foreach ($this->inputs as $name => $properties) {
            if ($properties['required'] && ($properties['submitted_value'] == null || $properties['submitted_value'] == '')) {
                array_push($required_failures, $name);
            } else if (isset($properties['validated'])) {
                if ($properties['validated'] === false) {
                    array_push($validation_failures, $name);
                }
            }
        }

        return array(
            'input_required_error' => sizeof($required_failures) > 0,
            'input_required_failures' => $required_failures,
            'input_validation_error' => sizeof($validation_failures) > 0,
            'input_validation_failures' => $validation_failures
        );
*/        
        return array();
    }

    public function is_missing(): bool
    {
        foreach ($this->inputs as $input_name) {
            if ($input_name->is_missing()) {
                return true;
            }
        }
        return false;
    }

    public function is_invalid(): bool
    {
        foreach ($this->inputs as $input_name) {
            if ($input_name->is_invalid()) {
                return true;
            }
        }
        return false;
    }

    private function read_input($input_name, mixed $properties): void
    {
        if (is_null($input_name)) {
            throw new InputException('Invalid input parameters.');
        } else {
            if (!is_array($properties)) {
                $properties = array('value' => $properties);
            }

            // set default values
            $input_required = $properties['required'] ?? false;
            $input_type     = $properties['type'] ?? 'var';
            $input_validate = $properties['validate'] ?? false;
            $input_sanitize = $properties['sanitize'] ?? false;
            $filter_options = $properties['filter_options'] ?? array();
            $filter_flags   = $properties['filter_flags'] ?? 0;
            $sanitize_flags = $properties['sanitize_flags'] ?? array();
            $input_value    = $properties['value'] ?? null;
            $submitted_value = null;
            $input_validated = null;

            if (strtolower($input_type) == 'get' && isset($_GET[$input_name])) {
                $input_value = $_GET[$input_name];
            } else if (strtolower($input_type) == 'post' && isset($_POST[$input_name])) {
                $input_value = $_POST[$input_name];
            }

            $submitted_value = $input_value;

            $filter_flags |= FILTER_NULL_ON_FAILURE;

            // check if required value was submitted or not
            $input_submitted = !($submitted_value == null || $submitted_value == '') ;

            // validate the input according to filter
            if ($input_validate != false) {
                $input_value = filter_var(
                    $input_value, 
                    $input_validate,
                    array('options' => $filter_options, 'flags' => $filter_flags)
                );

                if ($input_validate == FILTER_VALIDATE_BOOLEAN) {
                    $input_value = $input_value ?? false;
                }

                $input_validated = ($input_submitted ? $input_value !== null: null);
            }


            // sanitize the input according to filter
            if ($input_sanitize != false) {
                $input_value = filter_var(
                    $input_value, 
                    $input_sanitize,
                    $sanitize_flags
                );
            }

            // probably add more advanced filtering and santization here
            //

            // record final validated input
            $this->inputs[$input_name] = new InputObject(
                name: $input_name,
                value: $input_value,
                type: $input_type,
                submitted_value: $submitted_value,
                required: $input_required,
                submitted: $input_submitted,
                validate: $input_validate != false,
                validated: $input_validated,
            );
        }
    }

    // Request method checking
    public function request_method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function is_post()
    {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    public function is_get()
    {
        return $_SERVER['REQUEST_METHOD'] == 'GET';
    }

    public function is_put()
    {
        return $_SERVER['REQUEST_METHOD'] == 'PUT';
    }

    public function is_delete()
    {
        return $_SERVER['REQUEST_METHOD'] == 'DELETE';
    }

    // ArrayAccess Methods

    public function offsetSet($input_name, $properties = null): void
    {
        $this->read_input($input_name, $properties);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->inputs[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->inputs[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->inputs[$offset]) ? $this->inputs[$offset] : null;
    }    

    // Iterator methods
    public function current(): mixed {
        return current($this->inputs);
    }
    public function key(): mixed {
        return key($this->inputs);
    }
    public function next(): void {
        next($this->inputs);
    }
    public function rewind(): void {
        reset($this->inputs);
    }
    public function valid (): bool {
        return key($this->inputs) !== null;
    }
}

class InputObject 
{
    private $name;
    private $value;
    private $type;
    private $submitted_value;
    private $required;
    private $submitted;
    private $validate;
    private $validated;

    public function __construct(...$properties)
    {
        $this->name = $properties['name'];
        $this->value = $properties['value'];
        $this->type = $properties['type'];
        $this->submitted_value = $properties['submitted_value'];
        $this->required = $properties['required'];
        $this->submitted = $properties['submitted'];
        $this->validate = $properties['validate'];
        $this->validated = $properties['validated'];
    }

    public function __toString()
    {
        if ($this->value == null)
            return "";
        else
            return $this->value;
    }

    public function is_invalid(): bool
    {
        return $this->validate == true && $this->validated === false;
    }

    public function is_missing(): bool
    {
        return $this->required == true && $this->submitted === false;
    }

    public function is_required(): bool
    {
        return $this->required == true;
    }

    public function submitted_value()
    {
        return $this->submitted_value;
    }
}