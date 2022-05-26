<?php
/* 
	file:         router.class.php
	type:         Class Definition
	written by:   Aaron Bishop
	date:         
	description:  handles routing to app modules based on URL paths
*/

namespace Fzb;

use Exception;

class RouterException extends Exception { }

class Router
{
    private $url_route;
    private $routes = array();

    function __construct($modules_dir = null)
    {
        if ($modules_dir == null && defined('MODULES_DIR')) {
            $modules_dir = MODULES_DIR;
        }
        if ($modules_dir == null) {
            throw new RouterException("Modules directly not defined. Either define in app_settings or pass to Router on instantiation.");
        }

        if (!is_null(fzb_get_router())) {
            throw new RouterException("A router has already been instantiated.  Cannot create more than one instance");
        }

        $this->find_routes($modules_dir);
        $this->determine_route();

        $GLOBALS['FZB_ROUTER_OBJECT'] = $this;
    }

    // routes to the proper module based on uri path
    public function route()
    {
        // match the determined module to a found module from file system   
        foreach($this->routes as $route => $path) {
            if($route == $this->url_route) {
                require_once($path);
                return;
            }
        }

        require_once($this->routes["main"]);
        return;
    }

    private function determine_route()
    {
        $filename = explode("/", $_SERVER['SCRIPT_NAME']);
        $filename = end($filename);
        $route_string = explode($filename."/", $_SERVER['PHP_SELF']);
        $route_string = end($route_string);

        $route_components = explode("/", $route_string);

        while (count($route_components) > 0) {
            $search = join("/", $route_components);
            if (array_key_exists($search, $this->routes)) {
                $this->url_route = $search;
                break;
            }
            array_pop($route_components);
        }

        //$_ENV['URL_ROUTE'] = $this->url_route;
    }

    public function get_route()
    {
        return $this->url_route;
    }

    public function get_app_path()
    {
        return explode($this->url_route, $_SERVER['REQUEST_URI'], 2)[0];
    }

    // recursively searches for controllers in the specified directory and automagically generates route strings
    private function find_routes($parent_dir, $prefix='')
    {
        foreach (scandir($parent_dir) as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($parent_dir.'/'.$file)) {
                    $this->find_routes($parent_dir.'/'.$file, ($prefix ? $prefix."/" : '').$file);
                } else if (preg_match('/\.php$/', $file)) {
                    list($module, $ext) = explode('.', $file);
                    $this->routes[($prefix ? $prefix."/" : '').$module] = $parent_dir."/".$file;
                }
            }
        }
    }


};