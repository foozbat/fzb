<?php
/**
 * Class Model
 * 
 * Simple ORM.  Maps a class's public and private members to a specified database table.
 * Provides functionality to write and retrieve objects to/from the DB.
 * Allows raw access to the database if desired.
 * 
 * usage: class MyModel extends Fzb\Model
 * 
 * notes: By default, a Model's public and private members will be mapped to DB colums,
 *        if they exist.  Protected members are not written to the DB,
 *        so structure your models accordingly.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */


namespace Fzb;

use Exception;
use Iterator;

class ModelException extends Exception { }

abstract class Model implements Iterator
{
    const __primary_key__ = 'id';
    const __table__ = '';

    private $__db_id__ = 0;
    protected int $__iter__ = 0;

    /**
     * Constructor
     *
     * @param mixed ...$params values to set model members variables to upon creation
     */
    public function __construct(mixed ...$params)
    {
        // if a primary key is passed to constructor, set primary key member
        if (isset($params[self::__primary_key__]))
        {
            $this->{$this::__primary_key__} = $params[self::__primary_key__];
            $this->load();
        }
        
        // if any model params were passed to constructor, set them
        if (sizeof($params) > 0) 
        {
            $this->set_model_data($params);
        }
    }

    /**
     * Gets the current Database object
     *
     * @todo Add support for multiple concurrent databases
     * 
     * @return Database current Database object
     */
    static function db(): Database
    {
        $db = Database::get_instance();
        if (is_null($db)) {
            throw new ModelException("Fzb\Database object could not be found.  A database object must be instantiated before using this object.");
        }
        return $db;
    }

    /**
     * Gets the table associated with the model
     *
     * @todo Add support for defaulting table name to the class name, if __table__ not specified.
     * 
     * @return string database table used by the model
     */
    static function table(): string
    {
        $cls = get_called_class();
        $table = $cls::__table__;

        if ($table == '')
        {
            if ($pos = strrpos($cls, '\\')) {
                $cls = substr($cls, $pos + 1);
            }
            $table = strtolower($cls);
        }

        return $table;
    }

    /**
     * Gets a list of all of the models public and private member variables
     *
     * @return array list of all public and private member variables
     */
    function get_model_vars(): array
    {
        $table_columns = $this->db()->get_column_names($this->table());

        $arr = get_object_vars($this);

        foreach ($arr as $var => $val)
        {
            if ((str_starts_with($var, "__") && str_ends_with($var, "__")) ||
                !in_array($var, $table_columns)) {
                unset($arr[$var]);
            }
        }

        return $arr;
    }

    /**
     * Sets public and private member variables to the values passed as an associative array
     *
     * @param array $data data to set class members to
     * @return void
     */
    function set_model_data(array $data): void
    {
        $arr = $this->get_model_vars();

        foreach ($arr as $var => $val)
        {
            if (property_exists(get_class($this), $var) && isset($data[$var]) && !str_starts_with($var, "__") && !str_ends_with($var, "__")) {
                $this->{$var} = $data[$var];
            }
        }
    }

    /**
     * Saves the model's current data to the database
     *
     * @return bool if save was successful or not
     */
    function save(): bool
    {
        $data = $this->get_model_vars();

        $rows_affected = $this->db()->auto_insert_update(
            $this::__table__, 
            $data, 
            $this::__primary_key__ ?? null,
            $data[$this::__primary_key__] ?? null
        );

        if (!isset($data[$this::__primary_key__])) {
            $this->{$this::__primary_key__} = $this->db()->last_insert_id();
        }

        return $rows_affected > 0;
    }

    /**
     * Loads a model's data from the database
     *
     * @return bool if load was successful or not
     */
    function load(): bool
    {
        $query = "SELECT * FROM ".$this::__table__." WHERE ".$this::__primary_key__."=?";

        $data = $this->db()->selectrow_assoc($query, $this::__primary_key__);

        if ($data === false)
            return false;
        else
            $this->set_model_data($data);
        return true;
    }

    /**
     * Gets all model objects stored in DB
     *
     * @return mixed a single or array of model objects or null
     */
    static function get_all(): mixed
    {
        $ret_arr = array();
        $cls = get_called_class();
        $table = $cls::table();

        $cls::db()->prepare("SELECT * FROM $table");
        $cls::db()->execute();

        while ($row = $cls::db()->fetchrow_assoc())
        {
            array_push($ret_arr, new $cls(...$row));
        }

        if (sizeof($ret_arr) == 0)
            return null;
        else
            return sizeof($ret_arr) == 1 ? $ret_arr[0] : $ret_arr;   
    }

    /**
     * Gets model objects by where .. and clause of specified parameters
     *
     * @param mixed ...$params variadic parameters to be checked
     * @return mixed a single or array of model objects or null
     */
    static function get_by(mixed ...$params): mixed
    {
        $ret_arr = array();
        $cls = get_called_class();
        $table = $cls::table();

        $query = "SELECT * FROM $table";
        
        if (sizeof($params) > 0)
        {
            $table_columns = $cls::db()->get_column_names($table);
            $query_fields = array();
            $query_values = array();

            foreach ($params as $field => $value)
            {
                if (!in_array($field, $table_columns)) {
                    throw new ModelException("Table column '$field' does not exist.");
                }
                array_push($query_fields, "$field = ?");
                array_push($query_values, $value);
            }

            if (sizeof($query_values) > 0)
                $query .= " WHERE ".implode(" AND ", $query_fields);
        }

        $cls::db()->prepare($query);
        $cls::db()->execute(...$query_values);

        while ($row = $cls::db()->fetchrow_assoc())
        {
            array_push($ret_arr, new $cls(...$row));
        }

        if (sizeof($ret_arr) == 0)
            return null;
        else
            return sizeof($ret_arr) == 1 ? $ret_arr[0] : $ret_arr;      
    }

    // iterator methods
    public function current(): mixed { return $this; }
    public function next(): void     { $this->__iter__++; }
    public function valid(): bool    { return $this->__iter__ == 0; }
    public function key(): mixed     { return $this->__iter__; }
    public function rewind(): void   { $this->__iter__ = 0; }
}