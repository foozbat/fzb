<?php
/**
 * Class Config
 * 
 * Reads config from an .ini file.  Container for parse_ini_file results.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

namespace Fzb;

use Exception;

class ConfigException extends Exception { }

class Config
{
    private $config;

    /**
     * Constructor
     *
     * @param mixed ...$params
     * @throws ConfigException if config file cannot be found
     */
    public function __construct(mixed ...$params)
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

        register_config($this);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        unregister_config($this);
    }

    /**
     * Gets a specified section from the config as an associative array
     *
     * @param string $section
     * @return array assocative array of config section settings
     */
    public function get_settings(string $section): array
    {
        if (!isset($this->config[$section])) {
            throw new ConfigException("Configuration section not found.");
        }

        return $this->config[$section];
    }
}