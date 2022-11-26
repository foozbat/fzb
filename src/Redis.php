<?php
/**
 * Class Redis
 * 
 * Simple Redis client for FZB
 * 
 * usage: instantiate with $redis = new Fzb\Redis();
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

namespace Fzb;

use Exception;

class RedisException extends Exception { }

class Redis
{
    const REDIS_PORT = 6379;
    const CHUNK_SIZE = 4096;
    
    private $socket;

    private string $last_cmd;

    private static $instances = array();
    private static $active_instance_id = null;

    private $instance_id = 0;

    function __construct(mixed ...$options)
    {
        /**
         *  @todo add connection parameters
         */

        if (!isset($options['host'])) {
            throw new RedisException("Did not specify Redis host name");
        }

        if (!isset($options['port'])) {
            $options['port'] = $this::REDIS_PORT;
        }

        $this->socket = socket_create(AF_INET, SOCK_STREAM , SOL_TCP);
        $success = socket_connect($this->socket, $options['host'], $options['port']);

        if (!$success) {
            throw new RedisException("Could not connect to Redis server.");
        }

        // save myself in the array of instances
        if (isset($options['id'])) {
            if (isset(self::$instances[$options['id']])) {
                throw new RedisException("Cannot redeclare a specified instance of Fzb\Databse");
            }
            self::$instances[$options['id']] = $this;
            $this->instance_id = $options['id'];
        } else {
            $this->instance_id = array_push(self::$instances, $this) - 1;
        }

        if (self::$active_instance_id === null) {
            self::$active_instance_id = $this->instance_id;
        }        
    }

    /**
     * Retrieves the default or specified instance
     *
     * @param integer $instance_num
     * @return Redis
     */
    public static function get_instance(?int $instance_id = null): ?Redis
    {
        if ($instance_id === null)
            $instance_id = self::$active_instance_id;
        
        return self::$instances[$instance_id] ?? null;
    }

    function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Disconnects from the Redis server
     *
     * @return void
     */
    function disconnect()
    {
        socket_close($this->socket);
    }

    /**
     * tests a connection using the Redis PING command
     *
     * @return boolean true on PONG, false otherwise
     */
    public function test(): bool
    {
        $resp = $this->cmd("PING");

        if ($resp != "PONG") {
            return false;
        }
        return true;
    }

    /**
     * Executes a Redis command and returns the response
     *
     * @param mixed ...$args "COMMAND", "param", "param"...
     * @return mixed response
     */
    public function cmd(mixed ...$args): mixed
    {
        $cmd = "";

        $cmd .= "*".sizeof($args)."\r\n";
        foreach ($args as $arg) {
            //$arg = $this->sanitize($arg);
            $cmd .= "$".strlen($arg)."\r\n".$arg."\r\n";
        }

        $this->last_cmd = implode(' ', $args);
        $this->last_cmd_raw = $cmd;

        socket_write($this->socket, $cmd, strlen($cmd));

        $resp = $this->get_response();

        if ($resp instanceof RedisError) {
            throw new RedisException("Redis error: $resp.");
        }

        return $resp;
    }

    /**
     * Gets the response of an executed command
     * should only be called after executing a command
     *
     * @return mixed response
     */
    private function get_response(): mixed
    {
        $buf = '';
        $response = '';

        while (false !== ($bytes = socket_recv($this->socket, $buf, $this::CHUNK_SIZE, MSG_DONTWAIT))) {
            $response .= $buf;
        }

        //$ret_arr = $response;
        $ret_arr = $this->parse_response($response);

        return $ret_arr;        
    }

    /**
     * Parses a response from the server into a PHP data structure
     * Calls itself recursively to handle nested arrays
     *
     * @param string $response_str response string from Redis server
     * @param integer $elements number of elements to be processed
     * @return mixed properly structured data
     */
    public function parse_response(string &$response_str, $elements=1): mixed
    {
        $ret = array();

        while ($elements > 0) {
            // get first char
            $prefix = substr($response_str, 0, 1);
            // remove first char
            $response_str = substr($response_str, 1);
            // get string up to first delimiter
            $value = substr($response_str, 0, strpos($response_str, "\r\n"));
            // remove first string through first delimiter
            $response_str = substr($response_str, strpos($response_str, "\r\n")+2);

            // add to ret array according to prefix code
            // : int (value)
            // + simple string (value)
            // - error (value)
            // $ bulk string (length)
            // * array (length)
            if ($prefix  == ":") {
                array_push($ret, (int) $value);
            } else if ($prefix  == "+") {
                array_push($ret, $value);
            } else if ($prefix  == "-") {
                array_push($ret, new RedisError($value));
            } else if ($prefix  == "$") {
                if ($value == "-1") {
                    array_push($ret, null);
                } else {
                    $string_val = substr($response_str, 0, $value);
                    $response_str = substr($response_str, strpos($response_str, "\r\n")+2);
                    array_push($ret, $string_val);
                }
            } else if ($prefix  == "*" && is_numeric($value)) {
                if ($value < 0) {
                    array_push($ret, null);
                } else if ($value == 0) {
                    array_push($ret, array());
                } else {
                    array_push($ret, $this->parse_response($response_str, $value));
                }
            }
            $elements--;
        }

        return sizeof($ret) > 1 ? $ret : $ret[0] ?? null;
    }

    /**
     * Sanitize
     *
     * @param string $string
     * @return string
     */
    private function sanitize(string $string): string
    {
        /**
         * @todo implement this, if needed
         */

        return $string;
    }

    /**
     * Redis Command Aliases
     */

    /**
     * Sets a key to a value
     *
     * @param string $key
     * @param mixed $value
     * @return boolean success (OK)
     */
    public function set(string $key, mixed $value): bool
    {
        $resp = $this->cmd('SET', $key, $value);
        
        if ($resp != "OK") {
            return false;
        }
        return true;
    }

    /**
     * Gets a specified key
     *
     * @param string $key
     * @return mixed key value
     */
    public function get(string $key): mixed
    {
        $resp = $this->cmd('GET', $key);

        return $resp;
    }

    /**
     * Wrapper for Redis HSET using PHP arrays
     *
     * @param string $key key to insert
     * @param array $data associative array of hash data to insert
     * @return integer number of fields inserted
     */
    public function hset(string $key, array $data): int
    {
        $args = array();
        
        foreach ($data as $k => $v) {
            array_push($args, $k);
            array_push($args, $v);
        }

        var_dump($args);

        $resp = $this->cmd('HSET', $key, ...$args);
        var_dump($this->last_cmd_raw);
        return $resp;
    }

    /**
     * Wrapper for Redis HGET
     *
     * @param string $key key of Redis hash
     * @param string $field field to get from Redis hash
     * @return string|null value of field if set
     */
    public function hget(string $key, string $field): ?string
    {
        $resp = $this->cmd('HGET', $key, $field);
        return $resp;
    }

    /**
     * Wrapper for Redis HGETALL
     *
     * @param string $key key of Redis hash
     * @return mixed|null Redis hash data as an associative array or null
     */
    public function hgetall(string $key): mixed
    {
        $resp = $this->cmd('HGETALL', $key);

        if (sizeof($resp) == 0) {
            return false;
        }

        $ret = array();

        for ($i=0; $i<sizeof($resp); $i += 2) {
            $ret[$resp[$i]] = $resp[$i+1];
        }

        return $ret;        
    }

    /**
     * Wrapper for Redis HKEYS
     *
     * @param string $key key of Redis hash
     * @return mixed 
     */
    public function hkeys(string $key): mixed
    {
        $ret = $this->cmd('HKEYS', $key);
        return sizeof($ret) > 0 ? $ret : false;
    }

    /**
     * Wrapper for Redis HDEL
     *
     * @param string $key key of Redis hash
     * @param [type] ...$fields fields to delete from hash
     * @return integer number of fields deleted
     */
    public function hdel(string $key, ...$fields): int
    {
        return $this->cmd('HDEL', $key, ...$fields);
    }

    /**
     * Deletes all fields from a Redis hash
     *
     * @param string $key key of Redis hash
     * @return int number of fields deleted 
     */
    public function hdelall(string $key): int
    {
        $fields = $this->hkeys($key);

        return $this->hdel($key, ...$fields);
    }
}

class RedisError
{
    private string $error;

    function __construct(string $error)
    {
        $this->error = $error;
    }

    public function __toString()
    {
        return $this->error;
    }
}