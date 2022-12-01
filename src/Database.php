<?php
/**
 * Class Database
 * 
 * This class is a wrapper for PDO to reduce boilerplate and provide a cleaner, more Perl DBI-like interface.
 * Fully supports MySQL, PostgreSQL, and SQLite.
 * Partially supports the remainder of PDO supported database engines.
 * 
 * usage: Instantiate with $db = new Fzb\Database()
 *         can specify connection info manually to the constructor,
 *         specify a .ini file of connection info,
 *         or from a Fzb\Config object
 * 
 * @author Aaron Bishop (github.com/foozbat)
 * 
 * @todo Currently, statement handlers are handled internally.  Possibly refactor to allow developer to handle own $sth.
 */

namespace Fzb;

use PDO;
use Exception;

class DatabaseException extends Exception { }

class Database
{
    private $instance;
    private $pdo;
    private $pdo_options;
    private $pdo_sth = null;

    private static $instances = array();
    private static $active_instance_id = null;

    private $instance_id = 0;

    /**
     * Constructor
     *
     * @param mixed ...$options connection options
     */
    public function __construct(mixed ...$options)
    {
        /**
         * @todo Refactor this ugly constructor :(
         */

        if (sizeof($options) == 0) {
            try {
                $config = Config::get_instance();
            } catch (Exception $e) {
                throw new DatabaseException("Database connection info not specified. Either set in constructor or configure in a .ini file.");
            }
            $options = $config->get_settings('database');
        }

        if (isset($options['ini_file'])) {
            if (file_exists($options['ini_file'])) {
                $ini_settings = parse_ini_file($options['ini_file'], true);
            } else {
                throw new DatabaseException("Could not find .ini with database credentials");
            }
            
            $options = $ini_settings['database'];
        }

        if (isset($options['driver'])) {
            if ($options['driver'] == 'sqlite') {
                if (!isset($options['file'])) {
                    throw new DatabaseException("SQLite Db file not specified.");
                }
                $options['username'] = null;
                $options['password'] = null;
            }
        } else if (!isset($options['driver']) || !isset($options['host']) || !isset($options['username']) || !isset($options['password'])) {
            throw new DatabaseException("Database connection info not specified.");
        }
        
        $this->pdo_options = $options;
        $this->connect();

        // save myself in the array of instances
        if (isset($options['id'])) {
            if (isset(self::$instances[$options['id']])) {
                throw new DatabaseException("Cannot redeclare a specified instance of Fzb\Databse");
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
     * @return Database
     */
    public static function get_instance(?int $instance_id = null): ?Database
    {
        if ($instance_id === null) {
            $instance_id = self::$active_instance_id;
        }

        return self::$instances[$instance_id] ?? null;
    }

    /**
     * Sets the active database instance.
     *
     * @param integer $instance_id
     * @return void
     */
    public static function set_active_db(int $instance_id): void
    {
        if (array_key_exists($instance_id, self::$instances)) {
            self::$active_instance_id = $instance_id;
        } else {
            throw new DatabaseException("Specified DB instance does not exist.");
        }
    }

    /**
     * Connects to database using provided connection info
     *
     * @throws DatabaseException if connection could not be established
     * @return void
     */
    public function connect()
    {
        $options = [
            PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
        ];
        
        $dsn = "";
        if ($this->pdo_options['driver'] == 'sqlite') {
            $dsn = "sqlite:".$this->pdo_options['file'];
        } else {
            $dsn = $this->pdo_options['driver'] . ":host=" . $this->pdo_options['host'];
        }
        
        if (isset($this->pdo_options['database'])) {
            $dsn .= ';dbname=' . $this->pdo_options['database'];
        }
        if (isset($this->pdo_options['port'])) {
            $dsn .= ";port=" . $this->pdo_options['port'];
        }
        if (isset($pdo_options['charset'])) {
            $dsn .= ";charset=" . $this->pdo_options['charset'];
        }

        try {
            $this->pdo = new PDO($dsn, $this->pdo_options['username'], $this->pdo_options['password'], $options);
        } catch (\PDOException $e) {
           throw new DatabaseException( $e->getMessage() );
        }
    }

    /**
     * Disconnects from the database
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Prepares a query statement
     *
     * @param string $query query to be executed
     * @return void
     */
    public function prepare(string $query): void
    {
        // kill any existing sth, maybe add support for multiple sth's later
        if ($this->pdo_sth != null) {
            $this->finish();
        }

        $this->pdo_sth = $this->pdo->prepare($query);
    }

    /**
     * Executes a prepared statement
     *
     * @param mixed ...$params variadic parameters to be used in prepared statement
     * @return void
     */
    public function execute(mixed ...$params): void
    {
        if ($this->pdo_sth == null) {
            throw new DatabaseException("Cannot execute without preparing a query.");
        }

        $this->pdo_sth->execute($params);
    }

    /**
     * Closes the current database cursor
     *
     * @return void
     */
    public function finish(): void
    {
        $this->pdo_sth->closeCursor();
        $this->pdo_sth = null;
    }

    /**
     * Fetches the result of an executed statement as a regular array
     *      *
     * @return mixed array of results or null
     */
    public function fetchrow_array(): mixed
    {
        if ($this->pdo_sth == null) {
            throw new DatabaseException("Cannot fetch without executing a prepared query.");
        }
        return $this->pdo_sth->fetch(PDO::FETCH_NUM);
    }

    /**
     * Fetches the result of an executed statement as an associative array
     *
     * @return mixed associative array of results or null
     */
    public function fetchrow_assoc(): mixed
    {
        if ($this->pdo_sth == null) {
            throw new DatabaseException("Cannot fetch execute without executing a prepared query.");
        }

        return $this->pdo_sth->fetch(PDO::FETCH_ASSOC);
    }

    // 
    /**
     * Prepares and executes a query
     *
     * @param string $query query to be executed
     * @param mixed ...$params parameters to be bound to prepared statement
     * @return integer rows affected
     */
    public function query(string $query, mixed ...$params): int
    {
        $sth = $this->pdo->prepare($query);
        $sth->execute($params);
        $row_count = $sth->rowCount();
        $sth = null;

        return $row_count;
    }

    /**
     * Executes a query and returns the first row of the result as a normal array
     *
     * @param string $query query to be executed
     * @param mixed ...$params parameters to be bound to prepared statement
     * @return mixed query results as array or null
     */
    public function selectrow_array(string $query, mixed ...$params): mixed
    {
        $sth = $this->pdo->prepare($query);
        $sth->execute($params);

        return $sth->fetch(PDO::FETCH_NUM);
    }

    /**
     * Executes a query and returns the first row of the result as an associative array
     *
     * @param string $query query to be executed
     * @param mixed ...$params parameters to be bound to prepared statement
     * @return mixed query results as associative array or null
     */
    public function selectrow_assoc(string $query, mixed ...$params): mixed
    {
        $sth = $this->pdo->prepare($query);
        $sth->execute($params);

        return $sth->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Execues a query and returns the first column of each row
     *
     * @param string $query query to be executed
     * @param mixed ...$params parameters to be bound to prepared statement
     * @return mixed query results as array or null
     */
    public function selectcol_array(string $query, mixed ...$params): mixed
    {
        $sth = $this->pdo->prepare($query);
        $sth->execute($params);

        return $sth->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Last insert ID
     *
     * @return integer last insert ID
     */
    public function last_insert_id(): int
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin transatction
     *
     * @return boolean success
     */
    public function begin_transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commits a transaction
     *
     * @return boolean
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * A query builder which attempts to insert or update data into a specified table based on 
     * provided primary key and primary key value.
     *
     * @param string $table table to insert/update into
     * @param array $data_array associative array of data to be inserted
     * @param mixed $table_key table primary key
     * @param mixed $table_key_value table primary key value (for updates)
     * @return integer
     */
    public function auto_insert_update(string $table, array $data_array, mixed $table_key = null, mixed $table_key_value = null): int
    {
        $tables = $this->get_tables();

        if (!in_array($table, $tables)) {
            throw new DatabaseException("auto_insert_update: table '$table' does not exist.");
        }

        $table_columns = $this->get_column_names($table);

        $row_exists = 0;

        if ($table_key != null && $table_key_value != null) {
            if (!in_array($table_key, $table_columns)) {
                throw new DatabaseException("auto_insert_update: table key '$table_key' does not exist.");
            }

            $row_exists = $this->selectrow_array("SELECT COUNT(*) FROM $table WHERE $table_key = ?", $table_key_value);
        }
        
        $query = '';
        $insert_fields = array();
        $insert_qmarks = array();
        $update_fields = array();
        $query_values  = array();

        foreach ($data_array as $field => $value) {
            if (!in_array($field, $table_columns)) {
                throw new DatabaseException("auto_insert_update: table column '$field' does not exist.");
            }

            array_push($insert_fields, $field);
            array_push($insert_qmarks, "?");
            array_push($update_fields, "$field = ?");
            array_push($query_values, $value);
        }

        if ($row_exists) {
            $query = "UPDATE $table SET ".implode(", ", $update_fields)." WHERE $table_key = ?";
            array_push($query_values, $table_key_value);
        } else {
            $query = "INSERT INTO $table (".implode(", ", $insert_fields).") VALUES (".implode(", ", $insert_qmarks).")";
        }

        return $this->query($query, ...$query_values);
    }

    /**
     * Gets the column names of a specified table
     * 
     * @todo Add support for more DBs
     *
     * @param string $table table to be checked
     * @return mixed array of column names or null
     */
    public function get_column_names(string $table): mixed
    {
        if ($this->pdo_options["driver"] == "mysql") {
            return $this->selectcol_array("EXPLAIN $table");
        } else if ($this->pdo_options["driver"] == "sqlite") {
            return $this->selectcol_array("SELECT name FROM pragma_table_info('$table')");
        } else if ($this->pdo_options["driver"] == "pgsql") {
            return $this->selectcol_array("SELECT column_name FROM information_schema.columns WHERE table_name = ?", $table);
        } else {
            throw new DatabaseException("Database driver not supported.");
        }
    }

    /**
     * Gets a list of tables in the current database
     *
     * @todo Add support for more DBs
     *    
     * @return mixed array of table names or null
     */
    public function get_tables(): mixed
    {
        if ($this->pdo_options["driver"] == "mysql") {
            return $this->selectrow_array("SHOW TABLES");
        } else if ($this->pdo_options["driver"] == "sqlite") {
            return $this->selectrow_array("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        } else if ($this->pdo_options["driver"] == "pgsql") {
            return $this->selectrow_array("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'information_schema' AND schemaname != 'pg_catalog'");
        } else {
            throw new DatabaseException("Database driver not supported.");
        }
    }
}

