<?php
/* 
	file:         input.class.php
	type:         Class Definition
	written by:   Aaron Bishop
	description:  
        This class contains provides an interface to safely handle inputs from get/post/path with validation and santization.
    usage:
        Instantiate with $inputs = new Input();
        Define inputs with 
        Access inputs with $inputs['myinput']
*/

namespace Fzb;

use ArrayAccess;
use Iterator;
use Exception;

// exceptions
class InputDefinitionException extends Exception { }

//
class Inputs implements ArrayAccess, Iterator
{
    private $inputs = array();
    private $path_vars = array();

    // constructor can optionally receive an array of input definitions
    public function __construct(...$inputs)
    {
        // check to see if this is a websocket connection
        if (strpos($_SERVER['GATEWAY_INTERFACE'], 'websocketd-CGI') !== false) {
            GLOBAL $_GET;
            $_GET = array();
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }

        $this->read_path_var_values();

        $this->add_inputs($inputs);
    }

    private function read_path_var_values(): void
    {
        $path_string = explode($_ENV['URL_ROUTE'], $_SERVER['REQUEST_URI'], 2)[1];
        $path_string = explode('?', $path_string)[0];
        $path_string = ltrim($path_string, "/");

        $this->path_vars = explode("/", $path_string);
    }
    
    private function add_inputs($inputs): void
    {
        if (isset($inputs)) {
            if (is_array($inputs)) {
                foreach ($inputs as $name => $properties) {
                    //$this->offsetSet($name, $properties);
                    $this->read_input($name, $properties);
                }
            }
        }  
    }

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
    }

    private function read_input($input_name, $properties): void
    {
        if (is_null($input_name)) {
            throw new InputDefinitionException('Invalid input parameters.');
        } else if (!is_array($properties) && $properties != null) {
            throw new InputDefinitionException('Cannot assign a value to an input directly, use an array to define input parameters.');
        } else {
            // set default values
            $input_required = $properties['required'] ?? false;
            $input_type     = $properties['type'] ?? 'GET';
            $input_validate = $properties['validate'] ?? false;
            $input_sanitize = $properties['sanitize'] ?? false;
            $filter_options = $properties['filter_options'] ?? array();
            $filter_flags   = $properties['filter_flags'] ?? 0;
            $sanitize_flags = $properties['sanitize_flags'] ?? array();
            $input_value = null;
            $submitted_value = null;
            $input_validated = null;

            if ($input_type == 'GET' && isset($_GET[$input_name])) {
                $input_value = $_GET[$input_name];
            } else if ($input_type == 'POST' && isset($_POST[$input_name])) {
                $input_value = $_POST[$input_name];
            } else if ($input_type == 'PATH' && isset($this->path_vars[0])) {
                $input_value = array_shift($this->path_vars);
            }

            $submitted_value = $input_value;

            $filter_flags |= FILTER_NULL_ON_FAILURE;

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

                $input_validated = ($input_value !== null);
            }

            // check if required value was submitted or not
            $input_submitted = ($submitted_value == null || $submitted_value == '') ;

            // sanitize the input according to filter
            if (isset($properties['sanitize'])) {
                $input_value = filter_var(
                    $input_value, 
                    $input_sanitize,
                    $sanitize_flags
                );
            }

            // probably add more advanced filtering and santization here
            //

            // record final validated input
/*            $this->inputs[$input_name] = array(
                'value' => $input_value,
                'submitted_value' => $submitted_value,
                'required' => $input_required,
                'validated' => $input_validated,
            );  */
            $this->inputs[$input_name] = new InputObject(
                name: $input_name,
                value: $input_value,
                submitted_value: $submitted_value,
                required: $input_required,
                submitted: $input_submitted,
                validated: $input_validated,
            );
        }
    }

    public function request_method()
    {
        return $_SERVER['REQUEST_METHOD'];
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
        return isset($this->inputs[$offset]['value'] ) ? $this->inputs[$offset]['value'] : null;
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
    private $submitted_value;
    private $required;
    private $submitted;
    private $validated;

    public function __construct(...$properties)
    {
        $this->name = $properties['name'];
        $this->value = $properties['value'];
        $this->submitted_value = $properties['submitted_value'];
        $this->required = $properties['required'];
        $this->submitted = $properties['submitted'];
        $this->validated = $properties['validated'];
    }

    public function __toString()
    {
        if ($this->value == null)
            return "";
        else
            return $this->value;
    }

    public function validated()
    {
        return $this->validated;
    }

    public function submitted()
    {
        return $this->submitted;
    }

    public function required()
    {
        return $this->required;
    }

    public function submitted_value()
    {
        return $this->submitted_value;
    }
}