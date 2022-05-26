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

class DatabaseException extends Exception { }

class Database
{
	// DATA MEMBERS //
    private $instance;
    private $pdo;
    private $pdo_options;
    private $pdo_sth = null;

	// CONSTRUCTOR //
    public function __construct(...$options)
    {
        if (isset($options['ini_file'])) {
            if (file_exists($options['ini_file'])) {
                $ini_settings = parse_ini_file($options['ini_file'], true);
            } else {
                throw new DatabaseException("Could not find .ini with database credentials");
            }
            
            $options = $ini_settings['database'];
        }

        if (!isset($options['driver']) || !isset($options['host']) || !isset($options['username']) || !isset($options['password'])) { 
            throw new DatabaseException("Database host, username, or password not specified");
        }

        $this->pdo_options = $options;
        
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
        
        $dsn = $this->pdo_options['driver'] . ":host=" . $this->pdo_options['host'];
        
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
           throw new DatabaseConnectException( $e->getMessage() );
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function prepare($query): void
	{
        // kill any existing sth, maybe add support for multiple sth's later
        if ($this->pdo_sth != null) {
            $this->finish();
        }

        $this->pdo_sth = $this->pdo->prepare($query);
    }

    public function execute(...$params): void
    {
        if ($this->pdo_sth == null) {
            throw new DatabaseException("Cannot execute without preparing a query.");
        }
        $this->pdo_sth->execute($params);
    }

    public function finish(): void
    {
        $this->pdo_sth->closeCursor();
        $this->pdo_sth = null;
    }

    // operates on a prepared statement
    public function fetchrow_array(): array
    {
        if ($this->pdo_sth == null) {
            throw new DatabaseException("Cannot fetch without executing a prepared query.");
        }
        return $this->pdo_sth->fetch(PDO::FETCH_NUM);
    }

    public function fetchrow_assoc(): array
    {
        if ($this->pdo_sth == null) {
            throw new DatabaseException("Cannot fetch execute without executing a prepared query.");
        }

        return $this->pdo_sth->fetch(PDO::FETCH_ASSOC);
    }

	// executes a query and returns no rows
    public function query($query, ...$params): int
	{
        $sth = $this->pdo->prepare($query);
        $sth->execute($params);
        $row_count = $sth->rowCount();
        $sth = null;

        return $row_count;
    }

 	// executes a query and returns the first row of the result as a normal array
    public function selectrow_array($query, ...$params): array
	{
        $sth = $this->pdo->prepare($query);
        $sth->execute($params);

        return $sth->fetch(PDO::FETCH_NUM);
    }

    // executes a query and returns the first row of the result as an associative array
	public function selectrow_assoc($query, ...$params): array
	{
        $sth = $this->pdo->prepare($query);
        $sth->execute($params);

        return $sth->fetch(PDO::FETCH_ASSOC);
    }

    // execues a query and returns the first column of each row
	public function selectcol_array($query, ...$params): array
	{
        $sth = $this->pdo->prepare($query);
        $sth->execute($params);

        return $sth->fetchAll(PDO::FETCH_COLUMN);
    }

	public function last_insert_id(): int
	{
		return $pdo->lastInsertId();
	}

    // transactions
    public function begin_transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function auto_insert_update($table, $data_array, $table_key = null, $table_key_value = null): int
	{
        $table_found = false;
        $tables = $this->selectrow_array("SHOW TABLES");
        if (!in_array($table, $tables)) {
            throw new Exception("auto_insert_update: table '$table' does not exist.");
        }

        $query = "INSERT INTO `$table` SET ";
        $row_exists = 0;

        if ($table_key != null && $table_key_value != null) {
            $table_columns = $this->selectcol_array("EXPLAIN `$table`");
            if (!in_array($table_key, $table_columns)) {
                throw new Exception("auto_insert_update: table key '$table_key' does not exist.");
            }

            $row_exists = $this->selectrow_array("SELECT COUNT(*) FROM `$table` WHERE `$table_key` = ?", $table_key_value);

            if ($row_exists) {
                $query = "UPDATE `$table` SET ";
            }
        }
        
		$query_fields = array();
		$query_values = array();

		foreach ($data_array as $field => $value)
		{
            array_push($query_fields, "$field = ?");
            array_push($query_values, $value);
		}

		$query .= implode(", ", $query_fields);

		if ($row_exists) {
			$query .= " WHERE `$table_key` = ?";
            array_push($query_values, $table_key_value);
        }

        return $this->query($query, ...$query_values);
	}
}

