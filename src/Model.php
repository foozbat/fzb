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

declare(strict_types=1);

namespace Fzb;

use Exception;
use Iterator;
use ReflectionClass;
use DateTime;

class ModelException extends Exception { }

abstract class Model implements Iterator
{
    const __primary_key__ = 'id';
    const __table__ = '';

    public int $id;
    public DateTime $created_at;
    public DateTime $updated_at;

    private $__db_id__ = 0;
    protected int $__iter__ = 0;

    private static $__reserved_names__ = ['_page', '_per_page', '_order_by', '_limit'];

    /**
     * Constructor
     *
     * @param mixed ...$params values to set model members variables to upon creation
     */
    public function __construct(mixed ...$params)
    {
        // if derived model uses a different primary key, unset the default
        if ($this::__primary_key__ != 'id') {
            unset($this->{'id'});
        }

        // if a primary key is passed to constructor, set primary key member
        /*if (isset($params[self::__primary_key__])) {
            $this->{$this::__primary_key__} = $params[self::__primary_key__];
            if (!$this->load()) {
                throw new Exception('id_not_found');
            }
        }*/
        
        // if any model params were passed to constructor, set them
        if (sizeof($params) > 0) {
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

        if ($table == '') {
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
    private function get_mapped_properties(): array
    {
        $table_columns = $this->db()->get_column_names($this->table());

        $properties = $this->get_class_properties();

        foreach ($properties as $i => $property) {
            $name = $property->name;

            if ((str_starts_with($name, "__") && str_ends_with($name, "__")) ||
                !in_array($name, $table_columns)) {
                unset($properties[$i]);
            }
        }

        return $properties;
    }

    private function get_class_properties($cls=null, $types='public'){
        if ($cls === null) {
            $cls = get_called_class();
        }
        $ref = new ReflectionClass($cls);
    
        $props = $ref->getProperties();
        $ret_arr = [];
    
        foreach($props as $prop){
            $f = $prop->getName();
            $ret_arr[$f] = $prop;
        }
    
        if($parent_cls = $ref->getParentClass()){
            $parent_ret_arr = $this->get_class_properties($parent_cls->getName());//RECURSION
            if(count($parent_ret_arr) > 0) {
                $ret_arr = array_merge($parent_ret_arr, $ret_arr);
            }
        }
    
        return $ret_arr;
    }

    private function get_model_data() {
        $data = [];
        $properties = $this->get_mapped_properties();

        //var_dump($properties);

        foreach ($properties as $property) {
            $name = $property->name;

            if ($name == 'updated_at') {
                continue;
            }

            if (isset($this->{$name})) {
                switch ($property->getType()) {
                    case 'bool':
                        $data[$name] = (int) $this->{$name};
                        break;
                    case 'DateTime':
                        $data[$name] = $this->{$name}->format('Y-m-d H:i:s');
                        break;
                    default:
                        $data[$name] = $this->{$name};
                }
            }
        }

        return $data;
    }

    /**
     * Sets public and private member variables to the values passed as an associative array
     *
     * @param array $data data to set class members to
     * @return void
     */
    private function set_model_data(array $data): void
    {
        $properties = $this->get_class_properties();

        //var_dump($properties);

        foreach ($properties as $property) {
            $name = $property->name;

            if (array_key_exists($name, $data)) {
                switch ($property->getType()) {
                    case 'bool':
                        $this->{$name} = (bool) $data[$name];
                        break;
                    case 'int':
                        $this->{$name} = (int) $data[$name];
                        break;
                    case 'float':
                        $this->{$name} = (float) $data[$name];
                        break;
                    case 'DateTime':
                        $this->{$name} = new DateTime($data[$name]);
                        break;
                    default:
                        $this->{$name} = $data[$name];
                }
            }
            
            /*if (property_exists(get_class($this), $var) && isset($data[$var]) && !str_starts_with($var, "__") && !str_ends_with($var, "__")) {
                if (gettype($this->{$var}) == 'boolean') {
                    $this->{$var} = (bool) $data[$var];
                } else {
                    $this->{$var} = $data[$var];
                }
            }*/
        }
    }

    /**
     * Saves the model's current data to the database
     *
     * @return bool if save was successful or not
     */
    public function save(): bool
    {
        $data = $this->get_model_data();

        //var_dump($this::__primary_key__);
        //var_dump($data[$this::__primary_key__] ?? null);

        $rows_affected = $this->db()->auto_insert_update(
            $this::__table__, 
            $data, 
            $this::__primary_key__ ?? null,
            $data[$this::__primary_key__] ?? null
        );

        if (!isset($data[$this::__primary_key__])) {
            $this->{$this::__primary_key__} = (int) $this->db()->last_insert_id();
        }

        return $rows_affected > 0;
    }

    /**
     * Loads a model's data from the database
     *
     * @return bool if load was successful or not
     */
    public function load(): bool
    {
        $query = "SELECT * FROM ".$this::__table__." WHERE ".$this::__primary_key__."=?";

        $data = $this->db()->selectrow_assoc($query, $this->{$this::__primary_key__});

        var_dump($data);

        if ($data === false) {
            return false;
        } else {
            $this->set_model_data($data);
        }

        return true;
    }

    public function delete(): bool
    {
        if (isset($this->{$this::__primary_key__})) {
            $query = "DELETE FROM ".$this::__table__." WHERE ".$this::__primary_key__."=?";

            return $this->db()->query($query, $this->{$this::__primary_key__}) > 0;
        }
        
        return false;
    }

    /**
     * Gets all model objects stored in DB
     *
     * @return mixed a single or array of model objects or null
     */
    public static function get_all(mixed ...$params): mixed
    {
        $ret_arr = [];
        $cls = get_called_class();
        $table = $cls::table();

        $page = (int) $params['_page'] ?? null;
        $per_page = (int) $params['_per_page'] ?? null;

        $query = "SELECT * FROM $table";
        $query .= self::order_by($params);
        $query .= self::paginate($params);

        var_dump($query);

        $cls::db()->prepare($query);
        $cls::db()->execute();

        while ($row = $cls::db()->fetchrow_assoc()) {
            array_push($ret_arr, new $cls(...$row));
        }

        if (sizeof($ret_arr) == 0) {
            return null;
        } else {
            return sizeof($ret_arr) == 1 ? $ret_arr[0] : $ret_arr;
        }
    }

    /**
     * Gets model objects by where .. and clause of specified parameters
     *
     * @param mixed ...$params variadic parameters to be checked
     * @return mixed a single or array of model objects or null
     */
    public static function get_by(mixed ...$params): mixed
    {
        $ret_arr = [];
        $cls = get_called_class();
        $table = $cls::table();

        $query = "SELECT * FROM $table";
        
        if (sizeof($params) > 0) {

            [$params, $options] = self::get_options($params);

            $table_columns = $cls::db()->get_column_names($table);
            $query_fields = [];
            $query_values = [];

            foreach ($params as $field => $value) {
                if (!in_array($field, $table_columns)) {
                    throw new ModelException("Table column '$field' does not exist.");
                }

                array_push($query_fields, "$field = ?");
                array_push($query_values, $value);
            }

            if (sizeof($query_values) > 0) {
                $query .= " WHERE ".implode(" AND ", $query_fields);
            }
            
            $query .= self::order_by($options);
            $query .= self::paginate($options);
        }

        $cls::db()->prepare($query);
        $cls::db()->execute(...$query_values);

        while ($row = $cls::db()->fetchrow_assoc()) {
            array_push($ret_arr, new $cls(...$row));
        }

        if (sizeof($ret_arr) == 0) {
            return null;
        } else {
            return sizeof($ret_arr) == 1 ? $ret_arr[0] : $ret_arr;
        }
    }

    public static function get_count(): int
    {
        $ret_arr = [];
        $cls = get_called_class();
        $table = $cls::table();

        return (int) $cls::db()->selectcol_array("SELECT COUNT(*) FROM $table")[0];
    }

    public static function from_sql(string $query, mixed ...$params): mixed
    {
        $ret_arr = [];
        $cls = get_called_class();
        $table = $cls::table();

        [$params, $options] = self::get_options($params);

        $query .= self::order_by($options);
        $query .= self::paginate($options);

        $cls::db()->prepare($query);
        $cls::db()->execute(...$params);

        while ($row = $cls::db()->fetchrow_assoc()) {
            array_push($ret_arr, new $cls(...$row));
        }

        if (sizeof($ret_arr) == 0) {
            return null;
        } else {
            return sizeof($ret_arr) == 1 ? $ret_arr[0] : $ret_arr;
        }
    }

    private static function paginate(mixed $params): string {
        $paginate_str = "";

        if (isset($params['_limit'])) {
            throw new ModelException("Cannot use _limit when paginating.");
        }

        if (isset($params['_page']) && isset($params['_per_page'])) {
            $paginate_str = sprintf(' LIMIT %d, %d', 
                ((int) $params['_page'] - 1) * (int) $params['_per_page'], 
                (int) $params['_per_page']
            );
        }
        return $paginate_str;
    }

    private static function order_by(mixed $params): string {
        $order_by_str = "";
        $order_by = $params['_order_by'] ?? null;
        $orders = [];

        if ($order_by !==  null) {
            if (!is_array($order_by)) {
                $order_by = [$params['_order_by']];
            }

            foreach ($order_by as $k => $v) {
                if (is_int($k)) {
                    $k = $v;
                    $v = 'ASC';
                } else {
                    $v = strtoupper($v);
                }

                if ($v != 'ASC' && $v != 'DESC') {
                    throw new ModelException("Invalid option for order_by().  Order must be ASC or DESC");
                }

                $orders[] = "$k $v";
            }

            $order_by_str = " ORDER BY ".join(', ', $orders);
        }

        return $order_by_str;
    }

    private static function get_options(mixed $params): array
    {
        $ret_arr = [[],[]];

        foreach ($params as $k => $v) {
            if (in_array($k, self::$__reserved_names__)) {
                $ret_arr[1][$k] = $v;
            } else {
                $ret_arr[0][$k] = $v;
            }
        }

        return $ret_arr;
    }

    /**
     * Iterator Methods
     *   Allows the developer to iterate over a single model object or array of objects
     *   via foreach if desired.
     */

    public function current(): mixed
    {
        return $this;
    }

    public function next(): void
    {
        $this->__iter__++;
    }

    public function valid(): bool
    {
        return $this->__iter__ == 0;
    }

    public function key(): mixed
    {
        return $this->__iter__;
    }

    public function rewind(): void
    {
        $this->__iter__ = 0;
    }
}