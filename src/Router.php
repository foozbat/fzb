<?php
/* 
    file:         router.class.php
    type:         Class Definition
    written by:   Aaron Bishop
    date:         
    description:  handles routing to app controllers and routes based on URL paths
*/

namespace Fzb;

use Exception;

class RouterException extends Exception { }

class Router
{
    private $controller_route = "/";
    private $controllers = array();

    private $routes = array();
    private $route_path;

    private $variable_regex = "[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*";

    function __construct($controllers_dir = null)
    {
        if ($controllers_dir == null && defined('CONTROLLERS_DIR')) {
            $controllers_dir = CONTROLLERS_DIR;
        }
        if ($controllers_dir == null) {
            throw new RouterException("Controllers directory not defined. Either define in app_settings or pass to Router on instantiation.");
        }

        if (!is_null(get_router())) {
            throw new RouterException("A router has already been instantiated.  Cannot create more than one instance");
        }

        $this->route_path = rtrim($_SERVER['PATH_INFO'] ?? '/', '/');

        $this->find_controllers($controllers_dir);
        $this->determine_controller();

        register_router($this);
    }

    function __destruct()
    {
        unregister_router($this);
    }

    // returns path to the proper controller based on uri path
    public function get_controller_path()
    {
        // match the determined controller to a found controller from file system   
        foreach($this->controllers as $controller => $path) {
            if($controller == $this->controller_route) {
                return $path;
            }
        }

        return $this->controllers["main"];
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
            print("route def:".$route_string."\n");
            print("path ".$this->route_path."\n");

            print("\n");
            print_r($method);
            print("\n");
            //print_r($route_func);
            print("\n");
*/
            if (isset($this->routes[$route_string]))
                throw new RouterException("Attempting to redefine route \"$route_string\"");

            $this->routes[$route_string] = [
                'method' => $method,
                'func' => $func
            ];
        }
    }

    public function route()
    {
        //print("<pre>");
        //print("ROUTING...\n");
        //print_r($this->routes);

        foreach ($this->routes as $route_string => $route)
        {
            //print("CHECKING route: $route_string\n");

            $route_regex = preg_replace("/\{(.*?)\}/i", "(.*?)", $route_string);
            $route_regex = "~^".str_replace("/", "\/", $route_regex)."$~i";

            //print("regex: $route_regex\n");
            
            $is_match = preg_match($route_regex, $this->route_path);

            if (!in_array($_SERVER['REQUEST_METHOD'], $route['method']))
                return;

            //print("match? $is_match\n");
            $route_vars = array();
            $route_var_vals = array();

            if ($is_match) {
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
                return;
            }
        }
    }

    private function determine_controller()
    {
        $route_components = explode("/", ltrim($this->route_path, '/'));

        while (count($route_components) > 0) {
            $search = join("/", $route_components);
            if (array_key_exists($search, $this->controllers)) {
                $this->controller_route = $search;
                break;
            }
            array_pop($route_components);
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
    private function find_controllers($parent_dir, $prefix='')
    {
        foreach (scandir($parent_dir) as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($parent_dir.'/'.$file)) {
                    $this->find_controllers($parent_dir.'/'.$file, ($prefix ? $prefix."/" : '').$file);
                } else if (preg_match('/\.php$/', $file)) {
                    list($controller, $ext) = explode('.', $file);
                    $this->controllers[($prefix ? $prefix."/" : '').$controller] = $parent_dir."/".$file;
                }
            }
        }
    }


};