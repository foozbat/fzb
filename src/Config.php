<?php
/* 
	file:         Config.php
	type:         Class Definition
	written by:   Aaron Bishop
	date:         
	description:  reads config from an .ini file
*/

namespace Fzb;

use Exception;

class ConfigException extends Exception { }

class Config
{
    private $config;

    public function __construct(...$params)
    {
        if (isset($params['ini_file'])) {
            if (file_exists($params['ini_file'])) {
                $this->config = parse_ini_file($params['ini_file'], true);
            } else {
                throw new ConfigException("Could not find specified configuration file.");
            }
        } else {
            throw new ConfigException("Configuration file not specified.");
        }

        $GLOBALS['FZB_SETTINGS_OBJECT'] = $this;
    }

    public function get_settings($section)
    {
        if (!isset($this->config[$section])) {
            throw new ConfigException("Configuration section not found.");
        }

        return $this->config[$section];
    }
}