<?php
/**
 * 
 */

declare(strict_types=1);

namespace Fzb;

use Exception;
use Iterator;
use ReflectionClass;

class ModelException extends Exception {}

class Model implements Iterator
{
    public static ?Database $db = null;
    private static array $metadata = [];
    protected int $__iter__ = 0;
    private static array $__reserved_names__ = ['_page', '_per_page', '_order_by', '_limit', '_left_join'];

    /**
     * Constructor
     *
     * @param mixed ...$params values to set model members variables to upon creation
     */
    public function __construct(mixed ...$params)
    {
        self::init();

        // if any model params were passed to constructor, set them
        if (sizeof($params) > 0) {
            $this->set_model_data($params);
        }
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

        foreach (self::$metadata[$cls]['columns'] as $name => $attributes) {
            if (array_key_exists($name, $data)) {
                switch ($attributes['type']) {
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
                        $this->{$name} = new \DateTime($data[$name]);
                        break;
                    default:
                        $this->{$name} = $data[$name];
                }
            }
        }
    }

    /**
     * Gets data from the class properties that are orm-mapped
     *
     * @return array object data
     */
    private function get_model_data(): array {
        $cls = self::init();
        $data = [];

        foreach (self::$metadata[$cls]['columns'] as $name => $attributes) {
            if ($name == 'updated_at') {
                continue;
            }

            if (isset($this->{$name})) {
                switch ($attributes['type']) {
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
     * Saves the model's current data to the database
     *
     * @return bool if save was successful or not
     */
    public function save(): bool
    {
        $cls = self::init();
        $data = $this->get_model_data();

        $rows_affected = self::$db->auto_insert_update(
            self::$metadata[$cls]['table']->name, 
            $data, 
            self::$metadata[$cls]['primary_key'],
            $data[self::$metadata[$cls]['primary_key']] ?? null
        );

        return $rows_affected > 0;
    }

    /**
     * Loads a model's data from the database
     *
     * @return bool if load was successful or not
     */
    public function load(): bool
    {
        $cls = self::init();

        $query = "SELECT * FROM ".self::$metadata[$cls]['table']->name." WHERE ".self::$metadata[$cls]['primary_key']."=?";

        $data = self::$db->selectrow_assoc($query, $this->{self::$metadata[$cls]['primary_key']});

        if ($data === false) {
            return false;
        } else {
            $this->set_model_data($data);
        }

        return true;
    }

    public function delete(): bool
    {
        $cls = self::init();

        if (isset($this->{self::$metadata[$cls]['primary_key']})) {
            $query = "DELETE FROM ".self::$metadata[$cls]['table']->name." WHERE ".self::$metadata[$cls]['primary_key']."=?";

            return self::$db->query($query, $this->{self::$metadata[$cls]['primary_key']}) > 0;
        }
        
        return false;
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
        $table = self::$metadata[$cls]['table']->name;

        $query = "SELECT ";
        $query .= self::select_fields($params);
        $query .= "FROM $table";
        
        list($where, $query_values) = self::where($params);

        $query .= self::left_join($params);
        $query .= $where;
        $query .= self::order_by($params);
        $query .= self::paginate($params);

        self::$db->prepare($query);
        self::$db->execute(...$query_values);

        while ($row = self::$db->fetchrow_assoc()) {
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
        $table = self::$metadata[$cls]['table']->name;

        return (int) self::$db->selectcol_array("SELECT COUNT(*) FROM $table")[0];
    }

    public static function get_count_by(mixed ...$params): int
    {
        $cls = self::init();
        $table = self::$metadata[$cls]['table']->name;

        list($where, $query_values) = self::where($params);

        $query = "SELECT COUNT(*) FROM $table" . $where;

        return (int) self::$db->selectcol_array($query, ...$query_values)[0];
    }

    public static function from_sql(string $query, mixed ...$params): mixed
    {
        $cls     = self::init();
        $table   = self::$metadata[$cls]['table']->name;
        $ret_arr = [];

        [$params, $options] = self::get_options($params);

        $query .= self::order_by($options);
        $query .= self::paginate($options);

        self::$db->prepare($query);
        self::$db->execute(...$params);

        while ($row = self::$db->fetchrow_assoc()) {
            array_push($ret_arr, self::parse_result_fields($row, $params));
        }

        if (sizeof($ret_arr) == 0) {
            return null;
        } else {
            return sizeof($ret_arr) == 1 ? $ret_arr[0] : $ret_arr;
        }
    }

    public static function select_fields(mixed $params): string
    {
        $cls    = self::init();
        $table  = self::$metadata[$cls]['table']->name;
        $fields = array_keys(self::$metadata[$cls]['columns']);

        $fields = array_map(function ($field) use ($table, $cls) { 
            return "$table.$field  AS `$cls" . '__' . "$field`";
        }, $fields);

        if (isset($params['_left_join'])) {
            foreach ($params['_left_join'] as $join_cls => $join_params) {
                $join_cls = $join_cls::init();

                $join_table = self::$metadata[$join_cls]['table']->name;
                $join_fields = array_keys(self::$metadata[$join_cls]['columns']);

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
            $join_parent_property_type = self::$metadata[$cls]['columns'][$join_parent_property]['type'] ?? null;
            
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

        $table = self::$metadata[$cls]['table']->name;

        $where = '';
        $query_fields = [];
        $query_values = [];

        if (sizeof($params) > 0) {
            [$params, $options] = self::get_options($params);

            $table_columns = array_keys(self::$metadata[$cls]['columns']);

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

            $table = self::$metadata[$cls]['table']->name;

            foreach ($params['_left_join'] as $join_cls => $join_params) {
                list($table_key, $join_table_key) = $join_params;
                $join_table = self::$metadata[$join_cls]['table']->name;
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
        $table = self::$metadata[$cls]['table']->name;

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

    private static function init(bool $no_cache = false): string
    {
        $cls = get_called_class();

        // get db instance if it exists
        if (!(self::$db instanceof Database)) {
            self::$db = Database::get_instance();

            if (is_null(self::$db)) {
                throw new ModelException("Fzb\Database object could not be found.  A database object must be instantiated before using this object.");
            }
        }

        // load metadata if exists
        if (defined('ORM_CACHE_DIR') && !$no_cache) {
            $orm_cache_filename = ORM_CACHE_DIR . '/' . str_replace('\\', '_', $cls) . '.php';
            if (file_exists($orm_cache_filename)) {
                self::$metadata[$cls] = include $orm_cache_filename;
                return $cls;
            }
        } 

        // generate metadata from ORM attributes
        self::$metadata[$cls] = [
            'table' => new Model\Table(name: self::default_table_name()),
            'columns' => [],
            'primary_key' => null
        ];

        $heirarchy = self::get_reverse_reflection();

        // traverse from base class to derived
        foreach ($heirarchy as $r) {
            // class level metadata
            foreach ($r->getAttributes() as $attr) {
                $attr_name = $attr->getName();
                
                // Resolve the fully qualified class name
                if (!class_exists($attr_name)) {
                    // Try common namespace prefixes
                    if (class_exists("Fzb\\Model\\$attr_name")) {
                        $attr_name = "Fzb\\Model\\$attr_name";
                    } elseif (class_exists("Fzb\\$attr_name")) {
                        $attr_name = "Fzb\\$attr_name";
                    }
                }
                
                $attribute_cls = self::get_short_name($attr_name);

                if ($attribute_cls === 'Table') {
                    $args = $attr->getArguments();
                    self::$metadata[$cls]['table'] = new \Fzb\Model\Table(...$args);
                } else {
                    // other class-level attributes can be handled here
                }
            }

            // property level metadata
            foreach ($r->getProperties() as $property) {
                $property_name = $property->getName();

                foreach ($property->getAttributes() as $attr) {
                    $attr_name = $attr->getName();
                    
                    // Resolve the fully qualified class name
                    if (!class_exists($attr_name)) {
                        // Try common namespace patterns
                        $possible_names = [
                            "Fzb\\Model\\$attr_name",
                            "Fzb\\$attr_name"
                        ];
                        
                        foreach ($possible_names as $possible) {
                            if (class_exists($possible)) {
                                $attr_name = $possible;
                                break;
                            }
                        }
                        
                        if (!class_exists($attr_name)) {
                            throw new ModelException("Model attribute class " . $attr->getName() . " does not exist.  Don't forget use statement.");
                        }
                    }

                    $attribute_cls = self::get_short_name($attr_name);

                    if ($attribute_cls === 'Column') {
                        if (in_array($property_name, self::$__reserved_names__)) {
                            throw new ModelException("Property name '$property_name' is reserved for ORM operations. Please rename the property.");
                        }

                        self::$metadata[$cls]['columns'][$property_name] = [
                            'type' => $property->getType() ? $property->getType()->getName() : 'mixed',
                            'attributes' => []
                        ];
                    } else if ($attribute_cls === 'PrimaryKey') {
                        self::$metadata[$cls]['primary_key'] = $property_name;
                    }   

                    if (!array_key_exists($property_name, self::$metadata[$cls]['columns'])) {
                        throw new ModelException("ORM attribute '$attribute_cls' applied to property '$property_name' which is not defined as a Column.");
                    }

                    $attribute_instance = new $attr_name(...$attr->getArguments(), table_name: self::$metadata[$cls]['table']->name, column_name: $property_name);

                    array_push(self::$metadata[$cls]['columns'][$property_name]['attributes'], $attribute_instance);
                }
            }
        }

        // final validation
        if (is_null(self::$metadata[$cls]['primary_key'])) {
            throw new ModelException("No primary key defined for model class '$cls'.  A primary key must be defined using the PrimaryKey attribute.");
        }
        
        //var_dump(self::$metadata[$cls]);

        return $cls;
    }


    public static function migrate(bool $test_run = false): void
    {
        $cls = self::init();

        $old_metadata = self::$metadata[$cls] ?? null;
        self::init(no_cache: true);
        $new_metadata = self::$metadata[$cls];

        // check if table exists
        $table_exists = self::$db->selectrow_array("SHOW TABLES LIKE '{$new_metadata['table']->name}'")[0] ?? false;

        // if table doesn't exist, create a new one
        if(!$table_exists) {
            echo "Creating table '{$new_metadata['table']->name}'...\n";
            $sql = "CREATE TABLE IF NOT EXISTS `{$new_metadata['table']->name}` (\n";
            $columns = [];
            $table_keys = [];

            foreach ($new_metadata['columns'] as $column_name => $column_meta) {
                foreach ($column_meta['attributes'] as $attr) {
                    if ($attr instanceof Model\Column) {
                        $columns[] = $attr->to_sql();
                    } else if ($attr instanceof Model\PrimaryKey || $attr instanceof Model\ForeignKey || $attr instanceof Model\Index) {
                        $table_keys[] = $attr->to_sql();
                    }
                }
            }

            // assemble columns
            $sql .= implode(",\n  ", $columns);

            // assemble keys
            if (!empty($table_keys)) {
                $sql .= ",\n  " . implode(",\n  ", $table_keys);
            }

            $sql .= "\n) " . $new_metadata['table']->to_sql();

            echo $sql . "\n";
        
            if ($test_run) {
                return;
            }

            self::$db->query($sql);
        } else {
            echo "Altering table '{$new_metadata['table']->name}'...\n";
            $table_changed = false;
 
            $to_add = [];
            $to_modify = [];
            $to_drop = [];

            // Collect old attributes
            foreach ($old_metadata['columns'] as $column_name => $column_meta) {
                foreach ($column_meta['attributes'] as $attr) {
                    $attr_id = $attr->name ?? (get_class($attr) . '_' . $column_name);
                    $old_attrs[$attr_id] = $attr;
                }
            }

            // Collect new attributes
            foreach ($new_metadata['columns'] as $column_name => $column_meta) {
                foreach ($column_meta['attributes'] as $attr) {
                    $attr_id = $attr->name ?? (get_class($attr) . '_' . $column_name);
                    $new_attrs[$attr_id] = $attr;
                }
            }
            
            // Determine changes
            foreach ($new_attrs as $attr_id => $attr) {
                if (!isset($old_attrs[$attr_id])) {
                    $to_add[] = $attr;
                } else {
                    if (serialize($attr) !== serialize($old_attrs[$attr_id])) {
                        $to_modify[] = $attr;
                    }
                }
            }

            // Determine drops
            foreach ($old_attrs as $attr_id => $attr) {
                if (!isset($new_attrs[$attr_id])) {     
                    $to_drop[] = $attr;
                }
            }

            $table_changed = !empty($to_add) || !empty($to_modify) || !empty($to_drop);

            if ($table_changed) {
                $alter_sql = "ALTER TABLE `{$new_metadata['table']->name}` ";

                // add new attributes
                foreach ($to_add as $attr) {
                    if ($attr instanceof Model\Column) {
                        echo "Adding column '{$attr->name}'...\n";
                        $column_exists = self::$db->selectrow_array("SHOW COLUMNS FROM `{$new_metadata['table']->name}` LIKE '{$column_name}'")[0] ?? false;
                        if ($column_exists) {
                            continue;
                        } else {
                            self::$db->query($alter_sql . $attr->to_add_sql());
                        }
                    } else if ($attr instanceof Model\ForeignKey) {
                        self::$db->query($alter_sql . $attr->to_add_index_sql());
                        self::$db->query($alter_sql . $attr->to_add_fk_sql());
                    } else {
                        self::$db->query($alter_sql . $attr->to_add_sql());
                    }
                }

                // modify existing attributes
                foreach ($to_modify as $attr) {
                    if ($attr instanceof Model\ForeignKey) {
                        self::$db->query($alter_sql . $attr->to_drop_fk_sql());
                        self::$db->query($alter_sql . $attr->to_drop_index_sql());
                        self::$db->query($alter_sql . $attr->to_add_index_sql());
                        self::$db->query($alter_sql . $attr->to_add_fk_sql());
                    } else {
                        self::$db->query($alter_sql . $attr->to_modify_sql());
                    }
                }

                // drop removed attributes
                foreach ($to_drop as $attr) {
                    if ($attr instanceof Model\Column) {
                        continue; // columns are not dropped automatically
                    } else if ($attr instanceof Model\ForeignKey) {
                        self::$db->query($alter_sql . $attr->to_drop_fk_sql());
                        self::$db->query($alter_sql . $attr->to_drop_index_sql());
                    } else {
                        self::$db->query($alter_sql . $attr->to_drop_sql());
                    }
                }
            }
        }

        // if successful,save the computed orm-data to file if enabled
        if (defined('ORM_CACHE_DIR')) {
            $output_filename = ORM_CACHE_DIR . '/' . str_replace('\\', '_', $cls) . '.php';
            $output_code = "<?php\nreturn " . var_export(self::$metadata[$cls], true) . ";\n";
            file_put_contents($output_filename, $output_code);
        }
    }

    /**
     * Static Helper Methods
     */

    private static function get_reverse_reflection(): array
    {
        $cls = get_called_class();

        $ret = [];
        for ($r = new ReflectionClass($cls); $r; $r = $r->getParentClass()) {
            array_unshift($ret, $r);
        }

        return $ret;
    }

    private static function default_table_name(): string
    {
        $cls = get_called_class();
        return strtolower(self::get_short_name($cls));
    }

    private static function get_short_name(string $cls): string
    {
        $class_parts = explode('\\', $cls);
        return end($class_parts);
    }

    /**
     * Iterator Methods
     */

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