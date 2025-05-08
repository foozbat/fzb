<?php
declare(strict_types=1);

namespace Fzb;

use Exception;
use Iterator;
use ReflectionClass;
use DateTime;

/**
 * @internal
 */
class ModelException extends Exception { }

/**
 * Simple ORM base class for database-backed models.
 *
 * Maps public and private members to a table, provides persistence and retrieval,
 * and allows raw DB access for advanced use cases.
 *
 * ### Usage
 * ```php
 * class MyModel extends Fzb\Model {
 *     public string $my_field1;
 *     public string $my_field2;
 * }
 * ```
 *
 * ### Notes
 * - Public/private members are mapped to DB columns
 * - Protected members are ignored (not persisted)
 * - Iterable: allows the developer to iterate over a single model object or array of objects via foreach if desired.
 *
 * @author Aaron Bishop <https://github.com/foozbat>
 */
abstract class Model implements Iterator
{
    const __primary_key__ = 'id';
    const __table__ = '';

    public int $id;
    public DateTime $created_at;
    public DateTime $updated_at;

    private $__db_id__ = 0;
    protected int $__iter__ = 0;

    private static array $__meta__ = [];

    private static $__reserved_names__ = ['_page', '_per_page', '_order_by', '_limit', '_left_join'];

    /**
     * Constructor
     *
     * @param mixed ...$params values to set model members variables to upon creation
     */
    public function __construct(mixed ...$params)
    {
        self::init();

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

    private static function init(): string
    {
        $cls = get_called_class();
        
        // if metadata exists, do nothing
        if (isset(self::$__meta__[$cls])) {
            return $cls;
        }

        // try to load pre-computed orm metadata
        if (defined('ORM_CACHE_DIR')) {
            $orm_cache_filename = ORM_CACHE_DIR . '/' . str_replace('\\', '_', $cls) . '.php';
            if (file_exists($orm_cache_filename)) {
                self::$__meta__[$cls] = include $orm_cache_filename;
                return $cls;
            }
        } 
        
        // compute orm metadata if needed

        // set the table name to name specified in derived class
        $table = $cls::__table__;

        // default table name to the lowercased class name if table is not specified
        if ($table == '') {
            if ($pos = strrpos($cls, '\\')) {
                $cls = substr($cls, $pos + 1);
            }
            $table = strtolower($cls);
        }

        $schema = self::db()->get_table_schema($table);
        $properties = self::get_class_properties();
        $orm_map = [];
        $orm_fields = [];

        foreach ($schema as $column) {
            $orm_map[$column['Field']]['db_field_type'] = $column['Type'];
        }

        foreach ($properties as $property) {
            $orm_map[$property->name]['obj_property_type'] = (string) $property->getType();
            if (isset($orm_map[$property->name]['db_field_type'])) {
                array_push($orm_fields, $property->name);
            }
        }

        self::$__meta__[$cls] = [
            'table' => $table,
            'orm_fields' => $orm_fields,
            'orm_map' => $orm_map,
            'schema' => $schema
        ];

        // save the computed orm-data to file if enabled
        if (defined('ORM_CACHE_DIR')) {
            $output_filename = ORM_CACHE_DIR . '/' . str_replace('\\', '_', $cls) . '.php';
            $output_code = "<?php\nreturn " . var_export(self::$__meta__[$cls], true) . ";\n";
            file_put_contents($output_filename, $output_code);
        }

        //$bm->end();

        return $cls;
    }

    /**
     * Helper static function to get the merged public properties of the class, recursively
     *
     * @param string $cls
     * @param string $types
     * @return void
     */
    private static function get_class_properties(string $cls=null, $types='public'){
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
            $parent_ret_arr = self::get_class_properties($parent_cls->getName());//RECURSION
            if(count($parent_ret_arr) > 0) {
                $ret_arr = array_merge($parent_ret_arr, $ret_arr);
            }
        }
    
        return $ret_arr;
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
     * Gets data from the class properties that are orm-mapped
     *
     * @return array object data
     */
    private function get_model_data(): array {
        $cls = self::init();
        $properties = self::$__meta__[$cls]['orm_fields'];
        $data = [];

        foreach ($properties as $name) {
            if ($name == 'updated_at') {
                continue;
            }

            if (isset($this->{$name})) {
                $meta = self::$__meta__[$cls]['orm_map'][$name];

                switch ($meta['obj_property_type']) {
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
        $cls = self::init();
        $properties = self::$__meta__[$cls]['orm_fields'];

        foreach ($properties as $name) {
            if (array_key_exists($name, $data)) {
                $meta = self::$__meta__[$cls]['orm_map'][$name];

                switch ($meta['obj_property_type']) {
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
        $cls = self::init();

        $ret_arr = [];
        $table = self::$__meta__[$cls]['table'];

        $query = "SELECT * FROM $table";
        $query .= self::order_by($params);
        $query .= self::paginate($params);

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
        $cls = self::init();

        $ret_arr = [];
        $table = self::$__meta__[$cls]['table'];

        $query = "SELECT ";
        $query .= self::select_fields($params);
        $query .= "FROM $table";
        
        list($where, $query_values) = self::where($params);

        $query .= self::left_join($params);
        $query .= $where;
        $query .= self::order_by($params);
        $query .= self::paginate($params);

        $cls::db()->prepare($query);
        $cls::db()->execute(...$query_values);

        while ($row = $cls::db()->fetchrow_assoc()) {
            array_push($ret_arr, self::parse_result_fields($row, $params));
        }

        if (sizeof($ret_arr) == 0) {
            return null;
        } else {
            $ret = sizeof($ret_arr) == 1 ? $ret_arr[0] : $ret_arr;
            if (isset($params['_get_count'])) {
                $ret = [$ret, self::get_count_by(...$params)];
            }
            return $ret;
        }
    }

    public static function get_count(): int
    {
        $cls = self::init();
        $table = self::$__meta__[$cls]['table'];

        return (int) $cls::db()->selectcol_array("SELECT COUNT(*) FROM $table")[0];
    }

    public static function get_count_by(mixed ...$params): int
    {
        $cls = self::init();
        $table = self::$__meta__[$cls]['table'];

        list($where, $query_values) = self::where($params);

        $query = "SELECT COUNT(*) FROM $table" . $where;

        return (int) $cls::db()->selectcol_array($query, ...$query_values)[0];
    }

    public static function from_sql(string $query, mixed ...$params): mixed
    {
        $cls     = self::init();
        $table   = self::$__meta__[$cls]['table'];
        $ret_arr = [];

        [$params, $options] = self::get_options($params);

        $query .= self::order_by($options);
        $query .= self::paginate($options);

        $cls::db()->prepare($query);
        $cls::db()->execute(...$params);

        while ($row = $cls::db()->fetchrow_assoc()) {
            array_push($ret_arr, self::parse_result_fields($row, $params));
        }

        if (sizeof($ret_arr) == 0) {
            return null;
        } else {
            return sizeof($ret_arr) == 1 ? $ret_arr[0] : $ret_arr;
        }
    }

    public static function get_sql_fields(): string
    {
        $cls    = self::init();
        $table  = self::$__meta__[$cls]['table'];
        $fields = self::$__meta__[$cls]['orm_fields'];

        $fields = array_map(function ($field) use ($table, $cls) { 
            return "$table.$field  AS `$cls" . '__' . "$field`";
        }, $fields);

        return join(', ', $fields);
    }
    public static function select_fields(mixed $params): string
    {
        $cls    = self::init();
        $table  = self::$__meta__[$cls]['table'];
        $fields = self::$__meta__[$cls]['orm_fields'];

        $fields = array_map(function ($field) use ($table, $cls) { 
            return "$table.$field  AS `$cls" . '__' . "$field`";
        }, $fields);

        if (isset($params['_left_join'])) {
            foreach ($params['_left_join'] as $join_cls => $join_params) {
                $join_cls = $join_cls::init();

                $join_table = self::$__meta__[$join_cls]['table'];
                $join_fields = self::$__meta__[$join_cls]['orm_fields'];

                foreach ($join_fields as $join_field) {
                    array_push($fields, "$join_table.$join_field  AS `$join_cls" . '__' . "$join_field`");
                }
            }
        }

        return join(', ', $fields);
    }

    private static function parse_result_fields(array $row, mixed $params): mixed
    {
        $cls = self::init();

        $objects = [];
        $ret_array = [];
        $tmp_array = [];

        foreach ($row as $key => $value) {
            if (str_contains($key, '__')) {
                list ($obj, $field) = explode('__', $key, 2);
                $tmp_array[$obj][$field] = $value;
                unset($row[$key]);
            } else {
                $tmp_array[$key] = $value;
            }
        }

        foreach ($tmp_array as $key => $value) {
            $is_joining = sizeof($ret_array) > 0 && isset($params['_left_join']);
            $join_parent_property = $params['_left_join'][$key][2] ?? null;
            $join_parent_property_type = self::$__meta__[$cls]['orm_map'][$join_parent_property]['obj_property_type'] ?? null;
            
            if ($join_parent_property_type !== null) {
                $join_parent_property_type = ltrim($join_parent_property_type, '?');
            }

            if ($is_joining && $join_parent_property !== null && $join_parent_property_type !== null) {
                $ret_array[0]->$join_parent_property = new $key(...$value);
            } else {
                $ret_array[] = is_array($value) ? new $key(...$value) : $value;
            }
        }

        return sizeof($ret_array) == 1 ? $ret_array[0] : $ret_array;
    }

    private static function where(mixed $params): array
    {
        $cls = self::init();

        $table = self::$__meta__[$cls]['table'];

        $where = '';
        $query_fields = [];
        $query_values = [];

        if (sizeof($params) > 0) {
            [$params, $options] = self::get_options($params);

            $table_columns = self::$__meta__[$cls]['orm_fields']; //$cls::db()->get_column_names($table);

            foreach ($params as $field => $value) {
                if (!in_array($field, $table_columns)) {
                    throw new ModelException("Table column '$field' does not exist.");
                }

                array_push($query_fields, "$table.$field = ?");
                array_push($query_values, $value);
            }

            if (sizeof($query_values) > 0) {
                $where = " WHERE ".implode(" AND ", $query_fields);
            }
        }

        return [$where, $query_values];
    }

    private static function left_join(mixed $params): string
    {
        $cls = self::init();

        $left_join_str = '';

        if (isset($params['_left_join'])) {
            // do some error checking

            $table = $cls::__table__;

            foreach ($params['_left_join'] as $join_cls => $join_params) {
                list($table_key, $join_table_key) = $join_params;
                $join_table = $join_cls::__table__;

                $left_join_str .= " LEFT JOIN $join_table ON $table.$table_key = $join_table.$join_table_key";
            }
        }

        return $left_join_str;
    }

    private static function paginate(mixed $params): string
    {
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

    private static function order_by(mixed $params): string
    {
        $cls = self::init();
        $table = self::$__meta__[$cls]['table'];

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

                // if they didn't specify a table, add this table as default
                if (!str_contains($k, '.')) {
                    $k = "$table.$k";
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

    // Iterator Methods

    /**
      * @internal
      */
    public function current(): mixed
    {
        return $this;
    }

    /**
      * @internal
      */
    public function next(): void
    {
        $this->__iter__++;
    }

    /**
      * @internal
      */
    public function valid(): bool
    {
        return $this->__iter__ == 0;
    }

    /**
      * @internal
      */
    public function key(): mixed
    {
        return $this->__iter__;
    }

    /**
      * @internal
      */
    public function rewind(): void
    {
        $this->__iter__ = 0;
    }
}