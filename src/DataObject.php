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

use Exception;

abstract class DataObject
{
    const __primary_key__ = 'id';
    const __table__ = '';

    public function __construct(...$params)
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

    static function db()
    {
        $db = get_database();
        if (is_null($db)) {
            throw new Exception("Fzb\Database object could not be found.  A database object must be instantiated before using this object.");
        }
        return $db;
    }

    static function table()
    {
        $cls = get_called_class();
        $table = $cls::__table__;

        if ($table == '')
        {
            if ($pos = strrpos($cls, '\\')) 
                $cls = substr($cls, $pos + 1);
            $table = strtolower($cls);
        }

        return $table;
    }

    function test_get_class_vars()
    {
        return get_class_vars(__CLASS__);
    }

    function get_model_vars()
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

    function set_model_data($data)
    {
        $arr = $this->get_model_vars();

        foreach ($arr as $var => $val)
        {
            if (property_exists(get_class($this), $var) && isset($data[$var]) && !str_starts_with($var, "__") && !str_ends_with($var, "__"))
            {
                $this->{$var} = $data[$var];
            }
        }
    }

    function save()
    {
        $data = $this->get_model_vars();

        $this->db()->auto_insert_update(
            $this::__table__, 
            $data, 
            $this::__primary_key__ ?? null,
            $data[$this::__primary_key__] ?? null
        );

        $this->{$this::__primary_key__} = $this->db()->last_insert_id();
    }

    function load()
    {
        $query = "SELECT * FROM `".$this::__table__."` WHERE `".$this::__primary_key__."`=?";

        $data = $this->db()->selectrow_assoc($query, $this::__primary_key__);

        $this->set_model_data($data);
    }

    static function get_all()
    {
        $ret_arr = array();
        $cls = get_called_class();

        $cls::db()->prepare("SELECT * FROM `".$cls::__table__."`");
        $cls::db()->execute();

        while ($row = $cls::db()->fetchrow_assoc())
        {
            array_push($ret_arr, new $cls(...$row));
        }

        return $ret_arr;
    }
}