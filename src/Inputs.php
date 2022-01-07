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
use Exception;

// exceptions
class InputValidationException extends Exception { public $required_failures = array(); public $validation_failures = array(); }
class InputDefinitionException extends Exception { }

//
class Inputs implements ArrayAccess
{
    private $inputs = array();
    private $path_vars = array();

    // constructor can optionally receive an array of input definitions
    public function __construct(array $inputs = null)
    {
        // check to see if this is a websocket connection
        if (strpos($_SERVER['GATEWAY_INTERFACE'], 'websocketd-CGI') !== false) {
            GLOBAL $_GET;
            $_GET = array();
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        }

        $this->add_inputs($inputs);
    }

    public function add_inputs($inputs)
    {
        if (isset($inputs)) {
            if (is_array($inputs)) {
                foreach ($inputs as $name => $properties) {
                    //$this->offsetSet($name, $properties);
                    $this[$name] = $properties;
                }
            }
        }        
    }

    public function validate()
    {
        $validation_failures = array();
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

        if (sizeof($required_failures) > 0 || sizeof($validation_failures) > 0) {
            $exception = new InputValidationException();
            $exception->required_failures = $required_failures;
            $exception->validation_failures = $validation_failures;
            throw $exception;
        }
    }

    // ArrayAccess Methods
    public function offsetSet($input_name, $properties = null)
    {
        if (is_null($input_name)) {
            throw new InputDefinitionException('Invalid input parameters.');
        } else if ($input_name == '_path_scheme') {
            //print getenv("SCRIPT_NAME");
            
            //print "URI: ".$_SERVER['REQUEST_URI']."<br />";
            //print "ROUTE: ".$_ENV['URL_ROUTE']."<br />";

            $path_string = explode($_ENV['URL_ROUTE'], $_SERVER['REQUEST_URI'], 2)[1];
            $path_string = ltrim($path_string, "/");

            //print_r($path);

            $path_var_values = explode("/", $path_string);
            $path_var_names  = explode("/", $properties);

            //print_r($path_var_values);

            for ($i=0; $i<sizeof($path_var_names); $i++) {
                $this->path_vars[$path_var_names[$i]] = $path_var_values[$i] ?? null;
            }

            //print_r($this->path_vars);
            //print $path;

        } else if (!is_array($properties) && $properties != null) {
            throw new InputDefinitionException('Cannot assign a value to an input directly, use an array to define input parameters.');
        } else {
            // set default values
            $input_required = $properties['required'] ?? false;
            $input_type     = $properties['type'] ?? 'GET';
            $input_validate = $properties['validate'] ?? false;
            $input_sanitize = $properties['sanitize'] ?? false;
            $filter_options = $properties['filter_options'] ?? array();
            $filter_flags   = $properties['filter_flags'] ?? array();
            $sanitize_flags = $properties['sanitize_flags'] ?? array();
            $input_value = null;
            $submitted_value = null;
            $input_validated = null;

            if ($input_type == 'GET' && isset($_GET[$input_name])) {
                $input_value = $_GET[$input_name];
            } else if ($input_type == 'POST' && isset($_POST[$input_name])) {
                $input_value = $_POST[$input_name];
            } else if ($input_type == 'PATH'&& isset($this->path_vars[$input_name])) {
                $input_value = $this->path_vars[$input_name];
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
                    //print "itsabool";
                    $input_value = $input_value ?? false;
                    //print var_dump($input_value);
                }

                $input_validated = ($input_value !== null);

                //print var_dump($input_validated);
            }

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

            $this->inputs[$input_name] = array(
                'value' => $input_value,
                'submitted_value' => $submitted_value,
                'required' => $input_required,
                'validated' => $input_validated,
//                'options' => $filter_options,
//                'validate' => $input_validate,
//                'santize' => $input_sanitize
            );            
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->inputs[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->inputs[$offset]);
    }

    public function offsetGet($offset)
    {
        //$this->validate();
        return isset($this->inputs[$offset]['value'] ) ? $this->inputs[$offset]['value'] : null;
    }    
}