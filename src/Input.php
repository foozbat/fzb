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
 * @author  Aaron Bishop (github.com/foozbat)
 * 
 * @todo refactor class.  I am not happy with it's implementation.
 */

declare(strict_types=1);

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
        if (isset($_SERVER['GATEWAY_INTERFACE']) && strpos($_SERVER['GATEWAY_INTERFACE'], 'websocketd-CGI') !== false) {
            GLOBAL $_GET;
            $_GET = array();
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }

        $this->read_all_inputs($inputs);
    }

    public static function from_request(mixed ...$inputs): array {
        $validation = new Input(...$inputs);
        $ret_arr = [];

        foreach ($validation as $var => $input) {
            array_push($ret_arr, $input->value);
        }

        array_push($ret_arr, $validation);

        return $ret_arr;
    }

    /**
     * Reads a specified input name from get/post/etc and performs specified validation.
     *
     * @param string $input_name Name of input to be read
     * @param mixed $properties Input source and validation/santitization options
     * @return void
     * 
     * @todo Refactor to something cleaner
     */
    private function read_input(string $input_name, mixed $properties): void
    {
        // default values
        $input_required  = false;
        $input_type      = 'get';
        $input_validate  = false;
        $input_sanitize  = false;
        $filter_options  = array();
        $filter_flags    = 0;
        $sanitize_flags  = array();
        $input_value     = null;
        $submitted_value = null;
        $input_validated = null;

        if (is_null($input_name) && !is_string($properties) && !is_array($properties) ) {
            throw new InputException('Invalid input parameters.');
        }

        if (is_string($properties)) {
            $properties = preg_replace('/\s+/',  ' ', $properties);
            $properties = str_replace('\r', '', $properties);
            $properties = str_replace('\n', '', $properties);
            $properties = ltrim($properties);
            $properties = rtrim($properties);
            //$properties = explode(' ', $properties);
            $properties = preg_split("/'[^']*'(*SKIP)(*F)|\x20/", $properties);
        }

        if ($properties !== null) {
            foreach ($properties as $property) {
                $property = strtolower($property);
                
                if (strpos($property, ':') !== false) {
                    list($tag, $option) = explode(':', $property);
                }

                if ($property == 'get') {
                    $input_type = 'get';
                } else if ($property == 'post') {
                    $input_type = 'post';
                } else if ($property == 'path') {
                    $input_type = 'path';
                } else if ($property == 'required') {
                    $input_required = true;
                } else if ($tag == 'default') { 
                    if ($option == 'null') {
                        $input_value = null;
                    } else {
                        $input_value = $option;
                        $input_value = ltrim($input_value, "'");
                        $input_value = rtrim($input_value, "'");
                    }
                } else if ($tag == 'validate') {
                    $option = 'FILTER_VALIDATE_'.strtoupper($option);

                    if (defined($option)) {
                        $input_validate = constant($option);
                    } else {
                        throw new InputException('Invalid input validation option.');
                    }
                } else if ($tag == 'sanitize') {
                    $option = 'FILTER_SANITIZE_'.strtoupper($option);

                    if (defined($option)) {
                        $input_sanitize = constant($option);
                    } else {
                        throw new InputException('Invalid input sanitization option.');
                    }
                } else if ($tag == 'validate_flags:') {
                    $flags = explode(',', $options);
                }
            }
        }

        if ($input_type == 'get' && isset($_GET[$input_name])) {
            $input_value = $_GET[$input_name];
        } else if ($input_type == 'post' && isset($_POST[$input_name])) {
            $input_value = $_POST[$input_name];
        } else if ($input_type == 'path') {
            $router = Router::get_instance();
            if ($router === null) {
                throw new InputException("Defining a path var requires a router object to be instantiated and a route defined.");
            }
            $path_vars = $router->get_path_vars();

            if (isset($path_vars[$input_name])) {
                $input_value = $path_vars[$input_name];
            }
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
            name:  $input_name,
            value: $input_value,
            type:  $input_type,
            submitted_value: $submitted_value,

            is_required: $input_required,
            is_missing:  $input_required && $input_submitted === false,
            is_invalid:  $input_validate && $input_validated === false,
            /*
            submitted_value: $submitted_value,
            required: $input_required,
            submitted: $input_submitted,
            validate: $input_validate != false,
            validated: $input_validated,*/
        );

    }

    /**
     * Wrapper for read_input which supports an array of inputs.
     *
     * @param array $inputs inputs to be read from source
     * @return void
     */
    private function read_all_inputs(array $inputs): void
    {
        if (isset($inputs)) {
            if (is_array($inputs)) {
                foreach ($inputs as $name => $properties) {
                    if (is_int($name)) {
                        $name = $properties;
                        $properties = null;
                    }
                    $this->read_input($name, $properties);
                }
            }
        }
    }

    /**
     * Checks if any required field is missing
     *
     * @return boolean True if missing
     */
    public function is_missing(): bool
    {
        foreach ($this->inputs as $input_name) {
            if ($input_name->is_missing) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if any validated field is invalid
     *
     * @return boolean True if invalid
     */
    public function is_invalid(): bool
    {
        foreach ($this->inputs as $input_name) {
            if ($input_name->is_invalid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Request method checking
     *   Wrappers for $_SERVER['REQUEST_METHOD']
     */
    
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

    /**
     * ArrayAccess methods
     */

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

    /**
     * Iterator methods
     */
    
    public function current(): mixed
    {
        return current($this->inputs);
    }

    public function key(): mixed
    {
        return key($this->inputs);
    }

    public function next(): void
    {
        next($this->inputs);
    }

    public function rewind(): void
    {
        reset($this->inputs);
    }

    public function valid (): bool
    {
        return key($this->inputs) !== null;
    }
}

/**
 * InputObject Class
 * 
 * Container for input data and their associated validation results
 * 
 * @todo refactor
 */
class InputObject
{
    private string $name;
    private string $type;

    public mixed $value;
    public readonly mixed $submitted_value;

    public readonly bool $is_required;
    public readonly bool $is_missing;
    public readonly bool $is_invalid;

    function __construct(string $name, mixed $value, mixed $submitted_value, string $type, bool $is_required, bool $is_missing, bool $is_invalid)
    {
        $this->name  = $name;
        $this->value = $value;
        $this->type  = $type;

        $this->submitted_value = $submitted_value;
        $this->is_required = $is_missing;
        $this->is_missing  = $is_missing;        
        $this->is_invalid  = $is_invalid;
    }

    public function __toString()
    {
        if ($this->value == null)
            return "";
        else
            return (string) $this->value;
    }
}