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
    protected $__primary_key__ = 'id';

    function db()
    {
        $db = get_database();
        if (is_null($db)) {
            throw new Exception("Fzb\Database object could not be found.  A database object must be instantiated before using this object.");
        }
        return $db;
    }

    function test_get_class_vars()
    {
        print("checking " . __CLASS__ . "<br />");
        return get_class_vars(__CLASS__);
    }

    function get_model_vars()
    {
        $table_columns = $this->db()->selectcol_array("EXPLAIN `$table`");

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

    function save()
    {
        $data = get_model_vars($this);

        $this->db()->auto_insert_delete(
            $this->__table__, 
            $data, 
            $this->__primary_key__ ?? null,
            $arr[$this->__primary_key__] ?? null
        );
    }

    function load()
    {
        $this->db()->selectrow_assoc("SELECT * FROM `$this->__table__` WHERE `$this->__primary_key__`=?");
    }
}