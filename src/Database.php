<?php
/* 
	file:         database.class.php
	type:         Class Definition
	written by:   Aaron Bishop
	description:  
        This class is a wrapper for PDO to reduce boilerplate and provide a cleaner, more Perl DBI-like interface.
    usage:
        Instantiate with $inputs = new Database('type', 'hostname','username','password','database');
        Define inputs with 
        Access inputs with $inputs['myinput']
*/

namespace Fzb;

use PDO;
use Exception;

class DatabaseConnectException extends Exception { }

class Database
{
	// DATA MEMBERS //
    private $instance;
    private $connection;
    private $conn_options;

	// CONSTRUCTOR //
    public function __construct($options = array())
    {
        if (isset($options['ini_file'])) {
            if (file_exists($options['ini_file'])) {
                $ini_settings = parse_ini_file($options['ini_file'], true);
            } else {
                throw new DatabaseConnectException("Could not find .ini with database credentials");
            }
            

            $options = $ini_settings['database'];

            //print_r($options);
        }

        if (!isset($options['driver']) || !isset($options['host']) || !isset($options['username']) || !isset($options['password'])) { 
            throw new DatabaseConnectException("Database host, username, or password not specified");
        }

        $this->conn_options = $options;
        
        $this->connect();

        $GLOBALS['FZB_DATABASE_OBJECT'] = $this;
    }

    // DESTRUCTOR //
    public function __destruct()
    {
        $this->disconnect();
    }

    // METHODS //
    public function connect()
    {
        $options = [
            PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
        ];
        
        $dsn = $this->conn_options['driver'] . ":host=" . $this->conn_options['host'];
        
        if (isset($this->conn_options['database'])) {
            $dsn .= ';dbname=' . $this->conn_options['database'];
        }
        if (isset($this->conn_options['port'])) {
            $dsn .= ";port=" . $this->conn_options['port'];
        }
        if (isset($conn_options['charset'])) {
            $dsn .= ";charset=" . $this->conn_options['charset'];
        }
        
        try {
            $connection = new PDO($dsn, $this->conn_options['username'], $this->conn_options['password'], $options);
        } catch (\PDOException $e) {
           throw new DatabaseConnectException( $e->getMessage() );
        }
    }

    public function disconnect()
    {
        $this->connection = null;
    }

    function prepare($query)
	{

    }

	// executes a query and returns no rows
    /*function query($query)
	{
        $connection->query($query);
    }*/

 	// executes a query and returns the first row of the result as a normal array
    function selectrow_array()
	{
        print "selectrow_array()<br />";
    }

    // executes a query and returns the first row of the result as an associative array
	function selectrow_assoc()
    {

	}

    // execues a query and returns the first column of each row
	function selectcol_array()
	{
    
    }

	function last_insert_id()
	{
		return $connection->lastInsertId();
	}   
    
    function auto_query($table, $table_key, $table_key_value, $data_array)
	{
		/*$table           = mysql_escape_string($table);
		$table_key       = mysql_escape_string($table_key);
		$table_key_value = mysql_escape_string($table_key_value);

		$query = "INSERT INTO `$table` SET ";

		$row_exists = $this->selectrow_array("SELECT COUNT(*) FROM `$table` WHERE `$table_key` = '$table_key_value'");

		if ($row_exists)
			$query = "UPDATE `$table` SET ";

		$query_columns = array();

		$table_columns = $this->selectcol_array("EXPLAIN `$table`");

		foreach ($data_array as $field => $value)
		{
			if ($field != 'id' && $field != 'last_updated')
			{			
				if ($value != 'NOW()')
					$value = '"'.mysql_escape_string($value).'"';
		
				if (in_array($field, $table_columns))
					array_push($query_columns, "$field = $value");
			}
		}

		$query .= implode(", ", $query_columns);

		if ($row_exists)
			$query .= " WHERE `$table_key` = '$table_key_value'";

		return $query;*/
	}
}

