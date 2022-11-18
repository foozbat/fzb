<?php
/**
 * Class Fzb\Renderer
 * 
 * Renders a PHP template to the output buffer and flushed to the browser or returned as a string.
 * Templates use raw PHP, but execute in a sandboxed environment.
 * 
 * usage: Instantiate with $renderer = new Fzb\Renderer()
 * 
 * @author  Aaron Bishop (github.com/foozbat)
 */

 // TODO: handle HTML-safe, HTML-unsafe assignment of render vars
 // TODO: refactor assigning of inputs

namespace Fzb;

use Exception;

class RendererException extends Exception { }

class Renderer
{
    // DATA MEMBERS //
    private string $templates_dir;

    private array $render_vars = array();
    private array $reserved_var_names = ['_vars', '_base_path', 'html'];

    private array $global_state = array();

    /**
     * Constructor
     *
     * @param string|null $templates_dir Directory that holds all templates
     * @throws RendererException if the templates directory is not specified in the constructor or by define()
     */
    function __construct(string $templates_dir = null)
    {
        if ($templates_dir !== null) {
            $this->templates_dir = $templates_dir;
        } else if (defined('TEMPLATES_DIR')) {
            $this->templates_dir = TEMPLATES_DIR;
        } else {
            $this->templates_dir = "/templates";
        }

        if ($this->templates_dir == "") {
            throw new RendererException("Renderer requires the templates directory and extension to be defined.");
        }

        // register default render_vars;
        $router = Router::get_instance();
        if ($router !== null)
            $base_path = $router->get_app_base_path() ?? "";
        else
            $base_path = "";

        $this->render_vars['_base_path'] = ltrim($base_path, "/");
    }

    /**
     * Assigns data to the renderer as a render var
     *
     * @param string $name Unique identifier for render variable
     * @param mixed $value Data to be assigned to the render variable
     * @throws RendererException if the specified var name is reserved
     * @return void
     */
    public function assign(string $name, mixed $value)
    {
        if (in_array($name, $this->reserved_var_names)) {
            throw new RendererException("$name is a reserved Renderer variable name.");
        }

        if ($value instanceof Input) {
            $this->assign_input($value);
        } else if (is_array($value)) {
            foreach ($value as $key => $var) {
                $this->render_vars[$name][$key] = $var;
            }
        }

        $this->render_vars[$name] = $value;
    }

    /**
     * Flattens an associative array and assigns to render vars with key name as var name
     *
     * @param mixed $arr Array to be assigned as render vars
     * @throws RendererException if the method is not passed an associative array or Input object
     * @return void
     */
    public function assign_all(mixed $arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $name => $value) {
                if (is_int($name)) {
                    throw new RendererException("assign_all: Must pass an associative array or Input object");
                } else if ($arr instanceof Input) {
                    $this->assign_input($value);
                } else {
                    $this->assign($name, $value);
                }
            }
        }
    }

    /**
     * Assigns InputObjects contained with in an Input object to the renderer
     * extracting errors to separate variables for easy checking within a template
     * 
     * @param Input $input Input object to be assigned as render vars
     * @throws RendererException if method is not passed a Input object
     * @return void
     */
    public function assign_input(Input $input)
    {
        if ($input instanceof Input) {
            $this->assign('input_required_error', $input->is_missing());
            $this->assign('input_validation_error', $input->is_invalid());

            foreach ($input as $name => $input_obj) {
                $this->assign($name, (string) $input_obj);
                $this->assign($name."_submitted_value", $input_obj->submitted_value());
                $this->assign($name."_is_required", $input_obj->is_required());
                $this->assign($name."_is_missing", $input_obj->is_missing());
                $this->assign($name."_is_invalid", $input_obj->is_invalid());
            }
        } else {
            throw new RendererException("assign_input did not receive a valid Input object.");
        }
    }

    /**
     * Renders and displays a specified page
     *
     * @param string $template_file Template to be rendered
     * @return void
     */
    public function display(string $template_file)
    {
        ob_start();
        $this->render($template_file);
        ob_end_flush();
    }

    /**
     * Renders the template and returns output as a string instead of sending to the browser
     *
     * @param string $template_file Template to be rendered
     * @return string Rendered HTML string
     */
    public function render_as_string(string $template_file): string
    {
        ob_start();
        $this->render($template_file);
        return ob_get_clean();
    }

    /**
     * Internal method for the rendering of pages.
     *
     * @param string $template_file Template to be rendered
     * @return void
     */
    private function render(string $template_file)
    {
        // send nifty no cache headers
        // probably change this
        /*
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', FALSE);
        header('Pragma: no-cache');*/

        // sandbox the application state to limit rogue template damage
        error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
        $err_handler = set_error_handler(null);
        $this->sandbox_global_state();

        // call helper functions to isolate scope of template from this class
        _load_tpl($this->templates_dir.'/'.$template_file, $this->render_vars);

        // restore the state after sandbox
        $this->restore_global_state();
        if ($err_handler !== null)
            set_error_handler($err_handler);
        error_reporting(E_ALL);
    }

    /**
     * Send HTTP redirect header
     * Application should not send any more output to the browser after calling
     *
     * @param string $location
     * @return void
     */
    public function redirect(string $location)
    {
        header("Location: $location");
    }

    /**
     * Sandboxes default global vars so that rogue templates cannot access them
     *
     * @return void
     */
    private function sandbox_global_state()
    {
        if (isset($GLOBALS))  $this->global_state['GLOBALS']  = $GLOBALS;
        if (isset($_SERVER))  $this->global_state['_SERVER']  = $_SERVER;
        if (isset($_GET))     $this->global_state['_GET']     = $_GET;
        if (isset($_POST))    $this->global_state['_POST']    = $_POST;
        if (isset($_FILES))   $this->global_state['_FILES']   = $_FILES;
        if (isset($_COOKIE))  $this->global_state['_COOKIE']  = $_COOKIE;
        if (isset($_SESSION)) $this->global_state['_SESSION'] = $_COOKIE;
        if (isset($_REQUEST)) $this->global_state['_REQUEST'] = $_REQUEST;
        if (isset($_ENV))     $this->global_state['_ENV']     = $_ENV;

        foreach ($GLOBALS as $global => $val) {
            unset($GLOBALS[$global]);
        }
        unset($_SERVER);
        unset($_GET);
        unset($_POST);
        unset($_FILES);
        unset($_COOKIE);
        unset($_SESSION);
        unset($_REQUEST);
        unset($_ENV);
    }

    /**
     * Restores default global vars after rendering of template is complete
     *
     * @return void
     */
    private function restore_global_state()
    {
        GLOBAL $_ENV;
        GLOBAL $_REQUEST;
        GLOBAL $_SESSION;
        GLOBAL $_COOKIE;
        GLOBAL $_FILES;
        GLOBAL $_POST;
        GLOBAL $_GET;
        GLOBAL $_SERVER;
        GLOBAL $GLOBALS;

        if (isset($this->global_state['_ENV']))     $_ENV     = $this->global_state['_ENV'];
        if (isset($this->global_state['_REQUEST'])) $_REQUEST = $this->global_state['_REQUEST'];
        if (isset($this->global_state['_SESSION'])) $_SESSION = $this->global_state['_SESSION'];
        if (isset($this->global_state['_COOKIE']))  $_COOKIE  = $this->global_state['_COOKIE'];
        if (isset($this->global_state['_FILES']))   $_FILES   = $this->global_state['_FILES'];
        if (isset($this->global_state['_POST']))    $_POST    = $this->global_state['_POST'];
        if (isset($this->global_state['_GET']))     $_GET     = $this->global_state['_GET'];
        if (isset($this->global_state['_SERVER']))  $_SERVER  = $this->global_state['_SERVER'];
        
        if (isset($this->global_state['GLOBALS'])) {
            foreach ($this->global_state['GLOBALS'] as $global => $val) {
                $GLOBALS[$global] = $val;
            }
        }

        $this->global_state = array();
    }
}

/**
 * Helper function to isolate the template scope from the rest of the class
 *
 * @param string $_template_file
 * @param array $_vars
 * @throws RendererException if the specified template file could not be found
 * @return void
 */
function _load_tpl(string $_template_file, array $_vars)
{
    // create a local variable for each render var
    extract($_vars, EXTR_SKIP);
    unset($_vars);

    if (file_exists($_template_file)) {
        require_once($_template_file);
    } else {
        throw new RendererException("Renderer could not find the specified template file.");
    }
}