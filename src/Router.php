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

declare(strict_types=1);

namespace Fzb;

use Exception;

class RouterException extends Exception { }

class Router
{
    private string $route_path = '/';
    private string $controller_route = '/';
    private array $controllers = array();
    private bool $controller_exists = false;
    private string $controllers_dir;
    private string $default_controller;

    private array $routes = array();
    private string $route_prefix = "";
    private array $path_vars = array();

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
        if (self::$instance !== null) {
            throw new RouterException("A router has already been instantiated.  Cannot create more than one instance");
        } else {
            self::$instance = $this;
        }

        // set controllers dir, if any specified, otherwise project root
        if ($controllers_dir !== null) {
            $this->controllers_dir = $controllers_dir;
        } else if (defined('CONTROLLERS_DIR')) {
            $this->controllers_dir = constant('CONTROLLERS_DIR');
        } else {
            //$this->controllers_dir = __DIR__;
        }

        // validate controllers directory
        if ($controllers_dir !== null && !is_dir($controllers_dir)) {
            throw new RouterException("Specified controllers directory does not exist.");
        }

        // set default controller, if any
        if ($default_controller !== null) {
            $this->default_controller = $default_controller;
        } else if (defined('DEFAULT_CONTROLLER')) {
            $this->default_controller = constant('DEFAULT_CONTROLLER');
        } else {
            //$this->default_controller = 'index.php';
        }
        
        // find out the route path from the request URI
        $this->determine_route_path();

        // if we specified a controllers dir, find all controllers and determine the current one
        if ($this->controllers_dir !== null && $this->default_controller !== null) {
            $this->find_controllers($this->controllers_dir);
            $this->determine_controller();
        }
    }

    function __destruct()
    {
        self::$instance = null;
    }

    /**
     * interface for retrieving the Router singleton
     *
     * @return Router Router instance
     */
    public static function get_instance(): Router
    {
        if (self::$instance === null) {
            self::$instance = new Router();
        }

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
        $included_files = get_included_files();

        foreach (scandir($parent_dir) as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($parent_dir.'/'.$file)) {
                    $this->find_controllers($parent_dir.'/'.$file, $prefix.$file."/");
                } else if (preg_match('/\.php$/', $file)) {
                    list($controller, $ext) = explode('.', $file);
                    $controller_path = $parent_dir."/".$file;

                    if (!in_array($controller_path, $included_files)) {
                        $this->controllers[($prefix ? $prefix : '/').$controller] = $controller_path;
                    }
                }
            }
        }

        if ($prefix == '/' && $this->default_controller != null) {
            $this->controllers['/'] = $parent_dir.'/'.$this->default_controller;
        }
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
        if ($this->controller_exists) {
            return $this->controllers[$this->controller_route];
        } else {
            throw new RouterException("A controller could not be found for the specified URI path.  Specify a default controller to prevent this error.");
        }
    }

    /**
     * Determines which controller to use based on the leading elements in the request URI path
     *
     * @return void
     */
    private function determine_controller(): void
    {
        $route_components = explode("/", $this->route_path);

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
    public function get_controller_path(): ?string
    {
        if ($this->controller_exists) {
            return $this->controller_route;
        }

        return null;
    }

    public function get_all_controllers(): array
    {
        return array_keys($this->controllers);
    }

    // ROUTER METHODS

    /**
     * Gets the route path relative to the root directory of the application
     * Allows for the app to be in the document root or a subfolder
     *
     * @return void
     */
    public function determine_route_path(): void
    {
        $route_path = $_SERVER['REQUEST_URI'];

        $base_path = dirname($_SERVER['SCRIPT_NAME']);

        if ($base_path != '/') {
            $count = 1;
            $route_path = str_replace($base_path, '', $_SERVER['REQUEST_URI'], $count);
            $route_path = rtrim($route_path, '/');
        }

        if (isset($_SERVER['QUERY_STRING'])) {
            $route_path = str_replace("?".$_SERVER['QUERY_STRING'], '', $route_path);
        }

        $this->route_path = $route_path;
    }

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
        if (!is_array($path))   $path = array($path);
        if (!is_array($method)) $method = array($method);
        if (!is_callable($func)) {
            throw new RouterException("Callback function not provided for route.");
        }

        $method = array_map("strtoupper", $method);

        foreach ($path as $route_string) {
            if (!str_starts_with($route_string, '/')) {
                $route_string = '/'.$route_string;
            }

            if ($this->route_prefix != '') {
                $route_string = $this->route_prefix . $route_string;
            }

            if (isset($this->routes[$route_string])) {
                throw new RouterException("Attempting to redefine route \"$route_string\"");
            }

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
        foreach ($this->routes as $route_string => $route)
        {
            if ($route_string != '/') {
                $route_string = rtrim($route_string, '/');
            }

            $route_regex = preg_replace("/\{(.*?)\}/i", "(.*?)", $route_string);
            $route_regex = "~^".str_replace("/", "\/", $route_regex)."$~i";
            
            $is_match = preg_match($route_regex, $this->route_path);

            if ($is_match && in_array($_SERVER['REQUEST_METHOD'], $route['method'])) {
                $rslt1 = array();
                $rslt2 = array();
                preg_match_all($route_regex, $route_string, $rslt1);
                preg_match_all($route_regex, $this->route_path, $rslt2);

                if (sizeof($rslt1) != sizeof($rslt2))
                    throw new RouterException("Route parameter mismatch.");

                for ($i=0; $i<sizeof($rslt1); $i++) {
                    if ($i == 0) continue;
                    $var_name = $rslt1[$i][0];
                    $var_val  = urldecode($rslt2[$i][0]);
                    $var_val = explode('/', $var_val)[0]; // discard any trash after '/'

                    $var_name = ltrim($var_name, '{');
                    $var_name = rtrim($var_name, '}');
                    
                    // probably change from passing raw path var to var as Input
                    $this->path_vars[$var_name] = $var_val;
                }

                //call_user_func($route['func'], ...$this->path_vars);
                call_user_func($route['func']);
                return true;
            }
        }

        http_response_code(404);
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
     * @return string
     */
    public function get_app_base_path(): string
    {
        return dirname($_SERVER['SCRIPT_NAME']);
    }

    /**
     * Returns the current route
     *
     * @return string
     */
    public function get_route(): string
    {
        return $this->controller_route;
    }

    public function get_routes(): array
    {
        return $this->routes;
    }

    public function get_path_vars(): array
    {
        return $this->path_vars;
    }

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
}