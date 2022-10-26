<?php
/* 
    file:         router.class.php
    type:         Class Definition
    written by:   Aaron Bishop
    description:  handles routing to app controllers and routes based on URL paths
*/

namespace Fzb;

use Exception;

class RouterException extends Exception { }

class Router
{
    private $controller_route = "/";
    private $controllers = array();
    private $controller_exists = false;
    private $default_controller = null;

    private $routes = array();
    private $route_path;

    private $route_prefix = "";

    private $variable_regex = "[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*";

    function __construct($controllers_dir = null, $default_controller = null)
    {
        // I'm a singleton
        if (!is_null(get_router()))
            throw new RouterException("A router has already been instantiated.  Cannot create more than one instance");

        // validate controllers directory
        if ($controllers_dir == null && defined('CONTROLLERS_DIR'))
            $controllers_dir = CONTROLLERS_DIR;
        if (!is_dir($controllers_dir))
            throw new RouterException("Controllers directory does not exist.");

        // set default controller, if any
        if ($default_controller == null && defined('DEFAULT_CONTROLLER'))
            $this->default_controller = DEFAULT_CONTROLLER;
        else if ($default_controller != null)
            $this->default_controller = $default_controller;

        $this->route_path = rtrim($_SERVER['PATH_INFO'] ?? '/', '/');

        if ($controllers_dir) {
            $this->find_controllers($controllers_dir);
            $this->determine_controller();
        }

        register_router($this);
    }

    function __destruct()
    {
        unregister_router($this);
    }

    public function controller_exists(): bool
    {
        return $this->controller_exists;
    }

    // returns path to the proper controller based on uri path
    public function get_controller()
    {
        if ($this->controller_exists)
            return $this->controllers[$this->controller_route];
    }

    public function get(...$params)
    {
        $func = array_pop($params);
        $this->add(method: 'GET', path: $params, func: $func);
    }

    public function post(...$params)
    {
        $func = array_pop($params);
        $this->add(method: 'POST', path: $params, func: $func);
    }

    public function put(...$params)
    {
        $func = array_pop($params);
        $this->add(method: 'PUT', path: $params, func: $func);
    }

    public function delete(...$params)
    {
        $func = array_pop($params);
        $this->add(method: 'DELETE', path: $params, func: $func);
    }

    public function add($method='GET', $path=null, $func=null)
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
            print("path ".$this->route_path."\n");

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

    public function route(): bool
    {
        //print("<pre>");
        //print("ROUTING...\n");
        //print_r($this->routes);

        foreach ($this->routes as $route_string => $route)
        {
            $route_string = rtrim($route_string, '/');

            //print("CHECKING route: $route_string\n");

            $route_regex = preg_replace("/\{(.*?)\}/i", "(.*?)", $route_string);
            $route_regex = "~^".str_replace("/", "\/", $route_regex)."$~i";

            //print("regex: $route_regex\n");
            
            $is_match = preg_match($route_regex, $this->route_path);

            //print("match? $is_match\n");
            $route_vars = array();
            $route_var_vals = array();

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

    public function use_prefix($prefix)
    {
        if (!str_starts_with($prefix, '/'))
            $prefix = '/'.$prefix;

        $this->route_prefix = $prefix;
    }

    public function use_controller_prefix()
    {
        $this->use_prefix($this->controller_route);
    }

    private function determine_controller()
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

    public function get_route()
    {
        return $this->controller_route;
    }

    public function get_all_routes()
    {
        return $this->routes;
    }

    public function get_app_base_path()
    {
        return explode($this->controller_route, $_SERVER['REQUEST_URI'], 2)[0];
    }

    // recursively searches for controllers in the specified directory and automagically generates route strings
    private function find_controllers($parent_dir, $prefix='/')
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
};