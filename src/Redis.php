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

declare(strict_types=1);

namespace Fzb;

use Exception;

class RedisException extends Exception { }

class Redis
{
    const REDIS_PORT = 6379;
    const CHUNK_SIZE = 4096;
    
    private $socket;
    private $host;
    private $port;

    private string $last_cmd;

    private static $instances = array();
    private static $active_instance_id = null;

    private $instance_id = 0;

    /**
     * Constructor
     *
     * @todo add support for Unix domain sockets
     * 
     * @param mixed ...$options Connection options
     */
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

        $this->host = $options['host'];
        $this->port = $options['port'];

        $this->connect();

        // save myself in the array of instances
        if (isset($options['id'])) {
            if (isset(self::$instances[$options['id']])) {
                throw new RedisException("Cannot redeclare a specified instance of Fzb\Redis.");
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
     * Destructor
     */
    function __destruct()
    {
        $this->disconnect();
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

    public function connect() : void
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM , SOL_TCP);
        $success = socket_connect($this->socket, $this->host, $this->port);

        if (!$success) {
            throw new RedisException("Could not connect to Redis server.");
        }

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
            $cmd .= "$".strlen((string) $arg)."\r\n".$arg."\r\n";
        }

        $this->last_cmd = implode(' ', $args);

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
     * @throws RedisException on timeout or socket error
     */
    private function get_response(): mixed
    {
        $buf = '';
        $response = '';
        $timeout = 5; // seconds
        $start_time = time();

        while (true) {
            // Check for timeout
            if (time() - $start_time > $timeout) {
                throw new RedisException("Redis command timeout after {$timeout} seconds");
            }

            // Attempt to receive data
            $bytes = socket_recv($this->socket, $buf, $this::CHUNK_SIZE, MSG_DONTWAIT);
            
            if ($bytes === false) {
                $error = socket_last_error($this->socket);
                // EAGAIN/EWOULDBLOCK means no data available yet (non-blocking)
                if ($error !== SOCKET_EAGAIN && $error !== SOCKET_EWOULDBLOCK) {
                    throw new RedisException("Socket error: " . socket_strerror($error));
                }
                // No data available, small sleep to prevent busy-waiting
                usleep(10000); // 10ms
                continue;
            }
            
            if ($bytes === 0) {
                throw new RedisException("Connection closed by Redis server");
            }

            $response .= $buf;

            // Check if we have a complete response
            if ($this->is_complete_response($response)) {
                break;
            }
        }

        $ret_arr = $this->parse_response($response);

        return $ret_arr;        
    }

    /**
     * Checks if a Redis response is complete
     *
     * @param string $response response string to check
     * @return bool true if response is complete, false otherwise
     */
    private function is_complete_response(string $response): bool
    {
        if (empty($response)) {
            return false;
        }

        $prefix = $response[0];

        // Simple checks for common response types
        switch ($prefix) {
            case '+': // Simple string
            case '-': // Error
            case ':': // Integer
                return strpos($response, "\r\n") !== false;
            
            case '$': // Bulk string
                $crlf_pos = strpos($response, "\r\n");
                if ($crlf_pos === false) {
                    return false;
                }
                $length = (int)substr($response, 1, $crlf_pos - 1);
                if ($length === -1) {
                    return true; // Null bulk string
                }
                // Need: length prefix + \r\n + data + \r\n
                $needed = $crlf_pos + 2 + $length + 2;
                return strlen($response) >= $needed;
            
            case '*': // Array
                // Simple heuristic: check if response ends with \r\n
                // More robust parsing would require full protocol knowledge
                return substr($response, -2) === "\r\n";
            
            default:
                return false;
        }
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

            /**
             * Add to ret array according to prefix code:
             *  : int (value)
             *  + simple string (value)
             *  - error (value)
             *  $ bulk string (length)
             *  * array (length)
             */
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
                    $string_val = substr($response_str, 0, (int) $value);
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
    public function hset(string $key, array $data): ?int
    {
        $args = array();
        
        foreach ($data as $k => $v) {
            array_push($args, $k);
            array_push($args, $v);
        }

        $resp = $this->cmd('HSET', $key, ...$args);

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

        if ($resp === null || sizeof($resp) == 0) {
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
        return is_array($ret) && sizeof($ret) > 0 ? $ret : false;
    }

    /**
     * Wrapper for Redis HDEL
     *
     * @param string $key key of Redis hash
     * @param [type] ...$fields fields to delete from hash
     * @return integer number of fields deleted
     */
    public function hdel(string $key, ...$fields): ?int
    {
        return is_array($fields) ? $this->cmd('HDEL', $key, ...$fields) : null;
    }

    /**
     * Deletes all fields from a Redis hash
     *
     * @param string $key key of Redis hash
     * @return int number of fields deleted 
     */
    public function hdelall(string $key): ?int
    {
        $fields = $this->hkeys($key);

        return is_array($fields) ? $this->hdel($key, ...$fields) : null;
    }
}

/**
 * RedisError Class
 * 
 * Allows for typing a Redis error string
 */
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