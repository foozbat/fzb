<?php
/* 
	file:         router.class.php
	type:         Class Definition
	written by:   Aaron Bishop
	date:         
	description:  handles routing to app modules based on URL paths
*/

namespace Fzb;

class Router
{
    private $url_route;
    private $modules = array();

    function __construct($modules_dir)
    {
        $this->determine_route();
        $this->find_modules($modules_dir);
    }

    // routes to the proper module based on uri path
    public function route()
    {
        // match the determined module to a found module from file system   
        foreach($this->modules as $module => $path) {
            if($module == $this->url_route) {
                require_once($path);
                return;
            }
        }

        require_once($this->modules["main"]);
        return;
    }

    private function determine_route()
    {
        // get the first element of the path and exclude params
        $local_path = getenv("SCRIPT_NAME");
        $local_path = str_replace("index.php", "", $local_path);

        if(strpos($_SERVER['REQUEST_URI'] , $local_path) === 0)
            $route_string = substr($_SERVER['REQUEST_URI'] , strlen($local_path)).'';

        $route_string = explode("/", $route_string)[0];
        $route_string = explode("?", $route_string)[0];     
        
        $this->url_route = $route_string;

        $_ENV['URL_ROUTE'] = $route_string;
    }

    // recursively flatten all found modules into an array of [module/name] => 'path'
    private function find_modules($parent_dir, $prefix='')
    {
        foreach (scandir($parent_dir) as $file) {
            if ($file != '.' && $file != '..' && preg_match('/\.php$/', $file)) {
                list($module, $ext) = explode('.', $file);
                $this->modules[($prefix ? $prefix."/" : '').$module] = $parent_dir."/".$file;
            }
        }
        return;
    }

    public function get_route()
    {
        return $this->url_route;
    }

    /* recursive version
    private function find_modules($parent_dir, $prefix='')
    {
        foreach (scandir($parent_dir) as $file) {
            if ($file != '.' && $file != '..' && ) {
                if (is_dir($parent_dir.'/'.$file)) {
                    $this->find_modules($parent_dir.'/'.$file, ($prefix ? $prefix."/" : '').$file);
                } else if (preg_match('/\.php$/', $file)) {
                    list($module, $ext) = explode('.', $file);
                    $this->modules[($prefix ? $prefix."/" : '').$module] = $parent_dir."/".$file;
                }
            }
        }
    } */


};