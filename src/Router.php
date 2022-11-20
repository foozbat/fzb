<?php
/**
 * Class Router
 * 
 * Handles routing to app controllers (.php files) and routes (callbacks) based on URL paths
 * 
 * usage: Instantiate with $router = new Fzb\Router()
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */ 

namespace Fzb;

use Exception;

class RouterException extends Exception { }

class Router
{
    private string $controller_route = '/';
    private array $controllers = array();
    private bool $controller_exists = false;
    private string $default_controller;

    private array $routes = array();
    private string $route_prefix = "";

    private static $instance = null;

    const variable_regex = "[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*";

    /**
     * Constructor, implements singleton pattern
     *
     * @param string|null $controllers_dir
     * @param string|null $default_controller
     * @throws RouterException
     */
    function __construct(string $controllers_dir = null, string $default_controller = null)
    {
        // I'm a singleton
        if (self::$instance !== null)
            throw new RouterException("A router has already been instantiated.  Cannot create more than one instance");
        else
            self::$instance = $this;

        // validate controllers directory
        if ($controllers_dir == null && defined('CONTROLLERS_DIR'))
            $controllers_dir = CONTROLLERS_DIR;
        if (!is_dir($controllers_dir) && $controllers_dir !== null)
            throw new RouterException("Specified controllers directory does not exist.");

        // set default controller, if any
        if ($default_controller == null && defined('DEFAULT_CONTROLLER'))
            $this->default_controller = DEFAULT_CONTROLLER;
        else if ($default_controller !== null)
            $this->default_controller = $default_controller;

        if ($controllers_dir !== null && $default_controller !== null) {
            $this->find_controllers($controllers_dir);
            $this->determine_controller();
        }
    }

    /**
     * interface for retrieving the Router singleton
     *
     * @return Router Router instance
     */
    public static function get_instance(): Router
    {
        if (self::$instance === null)
            self::$instance = new Router();
        
        return self::$instance;
    }

    // CONTROLLER METHODS

    /**
     * Recursively searches for controllers in the specified directory and automagically generates route strings
     *
     * @param string $parent_dir Initially the controllers root dir
     * @param string $prefix used internally to handle subdirectory paths
     * @return void
     */
    private function find_controllers(string $parent_dir, string $prefix = '/')
    {
        foreach (scandir($parent_dir) as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($parent_dir.'/'.$file))
                    $this->find_controllers($parent_dir.'/'.$file, $prefix.$file."/");
                else if (preg_match('/\.php$/', $file)) {
                    list($controller, $ext) = explode('.', $file);
                    $this->controllers[($prefix ? $prefix : '/').$controller] = $parent_dir."/".$file;
                }
            }
        }

        if ($prefix == '/' && $this->default_controller != null)
            $this->controllers['/'] = $parent_dir.'/'.$this->default_controller;
    }
    
    /**
     * Identifies if a controller exists for the requested URI path
     *
     * @return boolean
     */
    public function controller_exists(): bool
    {
        return $this->controller_exists;
    }

    /**
     * Returns path to the proper controller based on uri path
     *
     * @return string absolute path to the controller .php file
     */
    public function get_controller(): string
    {
        if ($this->controller_exists)
            return $this->controllers[$this->controller_route];
        else
            throw new RouterException("A controller could not be found for the specified URI path.  Specify a default controller to prevent this error.");
    }

    /**
     * Determines which controller to use based on the leading elements in the request URI path
     *
     * @return void
     */
    private function determine_controller(): void
    {
        $route_path = rtrim($_SERVER['PATH_INFO'] ?? '/', '/');
        $route_components = explode("/", $route_path);

        while (count($route_components) > 0) {
            $search = join("/", $route_components);
            if (array_key_exists($search, $this->controllers)) {
                $this->controller_route = $search;
                break;
            }
            array_pop($route_components);
        }

        foreach($this->controllers as $controller => $path) {
            if($controller == $this->controller_route) {
                $this->controller_exists = true;
            }
        }
    }

    /**
     * GEts the URI path of the current controller
     *
     * @return string URI path of the current controller
     */
    public function get_controller_path(): string
    {
        if ($this->controller_exists)
        return $this->controller_route;
    }

    // ROUTER METHODS

    /**
     * Adds a new route to the router
     *
     * @param mixed $method HTTP request method or methods as either a string or array of strings
     * @param mixed $path endpoint path as either a string or array of strings, can contain {variables} to pass to the route callback
     * @param callable $func callback function to be executed for the route endpoint
     * @return void
     */
    public function add(mixed $method = 'GET', mixed $path = null, callable $func = null): void
    {
        /*
        $route_string = array_shift($params);
        $route_methods = $params['methods'];
        $route_func = null;

        if (!is_callable($params[0])) {
            $route_methods = array_shift($params);
            if (!is_array($route_methods))
                $route_methods = array($route_methods);
        }

        if (is_callable($params[0]))
            $route_func = array_shift($params);
*/
        //print("<pre>");
        //print("<b>ADDING ROUTES</b>\n");

        if (!is_array($path))   $path = array($path);
        if (!is_array($method)) $method = array($method);
        if (!is_callable($func))
            throw new RouterException("Callback function not provided for route.");

        $method = array_map("strtoupper", $method);

        foreach ($path as $route_string) {
/*
            print("route def: ".$route_string."\n");

            print("\n");
            print_r($method);
            print("\n");
            //print_r($route_func);
            print("\n");
*/
            if (!str_starts_with($route_string, '/'))
                $route_string = '/'.$route_string;

            if ($this->route_prefix != '')
                $route_string = $this->route_prefix . $route_string;

            //print($route_string."<br/>");

            if (isset($this->routes[$route_string]))
                throw new RouterException("Attempting to redefine route \"$route_string\"");

            $this->routes[$route_string] = [
                'method' => $method,
                'func' => $func
            ];
        }
    }

    /**
     * Alias for add method for GET endpoints
     *
     * @param mixed ...$params one or more strings specifying endpoints followed by the route callback
     * @return void
     */
    public function get(mixed ...$params): void
    {
        $func = array_pop($params);
        $this->add(method: 'GET', path: $params, func: $func);
    }

    /**
     * Alias for add method for POST endpoints
     *
     * @param mixed ...$params one or more strings specifying endpoints followed by the route callback
     * @return void
     */
    public function post(mixed ...$params): void
    {
        $func = array_pop($params);
        $this->add(method: 'POST', path: $params, func: $func);
    }

    /**
     * Alias for add method for PUT endpoints
     *
     * @param mixed ...$params one or more strings specifying endpoints followed by the route callback
     * @return void
     */
    public function put(mixed ...$params): void
    {
        $func = array_pop($params);
        $this->add(method: 'PUT', path: $params, func: $func);
    }

    /**
     * Alias for add method for DELETE endpoints
     *
     * @param mixed ...$params one or more strings specifying endpoints followed by the route callback
     * @return void
     */
    public function delete(mixed ...$params): void
    {
        $func = array_pop($params);
        $this->add(method: 'DELETE', path: $params, func: $func);
    }

    /**
     * Routes to the endpoint specified in the request URI path
     *
     * @return boolean true if route is found, false if route is not found
     */   
    public function route(): bool
    {
        //print("<pre>");
        //print("ROUTING...\n");
        //print_r($this->routes);
        $route_path = rtrim($_SERVER['PATH_INFO'] ?? '/', '/');

        foreach ($this->routes as $route_string => $route)
        {
            $route_string = rtrim($route_string, '/');

            //print("CHECKING route: $route_string\n");

            $route_regex = preg_replace("/\{(.*?)\}/i", "(.*?)", $route_string);
            $route_regex = "~^".str_replace("/", "\/", $route_regex)."$~i";

            //print("regex: $route_regex\n");
            
            $is_match = preg_match($route_regex, $route_path);

            //print("match? $is_match\n");
            $route_vars = array();
            $route_var_vals = array();

            if ($is_match && in_array($_SERVER['REQUEST_METHOD'], $route['method'])) {
                $rslt1 = array();
                $rslt2 = array();
                preg_match_all($route_regex, $route_string, $rslt1);
                preg_match_all($route_regex, $route_path, $rslt2);

                if (sizeof($rslt1) != sizeof($rslt2))
                    throw new RouterException("Route parameter mismatch.");

                for ($i=0; $i<sizeof($rslt1); $i++) {
                    if ($i == 0) continue;
                    $var_name = $rslt1[$i][0];
                    $var_val  = $rslt2[$i][0];

                    $var_name = ltrim($var_name, '{');
                    $var_name = rtrim($var_name, '}');
                    
                    $route_vars[$var_name] = $var_val;
                }

                /*
                print("vars:\n");
                print_r($route_vars);
                print_r($route['func']);
                print("\n");
                */
                
                call_user_func($route['func'], ...$route_vars);
                return true;
            }
        }

        return false;
    }

    /**
     * Allows a prefix to be used for all endpoints after calling
     * 
     * @param string $prefix prefix to be prepended to all endpoints
     * @return void
     */
    public function use_prefix(string $prefix): void
    {
        if (!str_starts_with($prefix, '/'))
            $prefix = '/'.$prefix;

        $this->route_prefix = $prefix;
    }

    /**
     * Alias for use_prefix() that uses the specified controller as the route prefix
     *
     * @return void
     */
    public function use_controller_prefix(): void
    {
        $this->use_prefix($this->controller_route);
    }

    /**
     * Gets the pase path of the application. For use when application is in a subdirectory of the webroot
     *
     * @return void
     */
    public function get_app_base_path()
    {
        return explode($this->controller_route, $_SERVER['REQUEST_URI'], 2)[0];
    }

    /**
     * Returns the current route
     *
     * @return void
     */
    public function get_route()
    {
        return $this->controller_route;
    }
}