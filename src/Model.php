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
                        $this->{$name} = new DateTime($data[$name]);
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
        $query = "SELECT * FROM ".$this::__table__." WHERE ".$this::__primary_key__."=?";

        $data = self::$db->selectrow_assoc($query, $this->{$this::__primary_key__});

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

            return self::$db->query($query, $this->{$this::__primary_key__}) > 0;
        }
        
        return false;
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
                $attribute_cls = self::get_short_name($attr->getName());

                if ($attribute_cls === 'Table') {
                    self::$metadata[$cls]['table'] = $attr->newInstance();
                } else {
                    // other class-level attributes can be handled here
                }
            }

            // property level metadata
            foreach ($r->getProperties() as $property) {
                $property_name = $property->getName();

                foreach ($property->getAttributes() as $attr) {
                    if (!class_exists($attr->getName())) {
                        throw new ModelException("Model attribute class " . $attr->getName() . " does not exist.  Don't forget use statement.");
                    }

                    $attribute_cls = self::get_short_name($attr->getName());

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

                    $attr_cls = $attr->getName();
                    $attribute_instance = new $attr_cls(...$attr->getArguments(), table_name: self::$metadata[$cls]['table']->name, column_name: $property_name);

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

    // ai generated slop, fix as needed
    public static function migrate(bool $test_run = false): void
    {
        $cls = self::init();

        $old_metadata = self::$metadata[$cls] ?? null;
        $table_changed = false;
        $changes = [
            'columns_to_add' => [],
            'columns_to_modify' => [],
            'keys_to_add' => [],
            'keys_to_drop' => [],
            'keys_to_modify' => [],
        ];

        self::init(no_cache: true);
        $metadata = self::$metadata[$cls];
        
        // compare old and new metadata to determine changes
        if ($old_metadata !== null) {
            // Check for new or modified columns
            foreach ($metadata['columns'] as $column_name => $column_meta) {
                if (!isset($old_metadata['columns'][$column_name])) {
                    // New column
                    array_push($changes['columns_to_add'], $column_name);
                } else {
                    // Check if column definition changed
                    $new_column_attr = $column_meta['attributes'][0];
                    $old_column_attr = $old_metadata['columns'][$column_name]['attributes'][0];
                    
                    if (serialize($new_column_attr) !== serialize($old_column_attr)) {
                        array_push($changes['columns_to_modify'], $column_name);
                    }
                }
            }

            // Check for new, modified, or dropped keys (indexes, foreign keys, etc.)
            $old_keys = [];
            $new_keys = [];

            // Collect old keys
            foreach ($old_metadata['columns'] as $column_name => $column_meta) {
                foreach ($column_meta['attributes'] as $attr) {
                    if ($attr instanceof Model\PrimaryKey || $attr instanceof Model\ForeignKey || $attr instanceof Model\Index) {
                        $key_id = $attr->name ?? (get_class($attr) . '_' . $column_name);
                        $old_keys[$key_id] = $attr;
                    }
                }
            }

            // Collect new keys
            foreach ($metadata['columns'] as $column_name => $column_meta) {
                foreach ($column_meta['attributes'] as $attr) {
                    if ($attr instanceof Model\PrimaryKey || $attr instanceof Model\ForeignKey || $attr instanceof Model\Index) {
                        $key_id = $attr->name ?? (get_class($attr) . '_' . $column_name);
                        $new_keys[$key_id] = $attr;
                    }
                }
            }

            // Find keys to add
            foreach ($new_keys as $key_id => $attr) {
                if (!isset($old_keys[$key_id])) {
                    $changes['keys_to_add'][] = $attr;
                } else {
                    // Check if key definition changed
                    if (serialize($attr) !== serialize($old_keys[$key_id])) {
                        $changes['keys_to_modify'][] = $attr;
                    }
                }
            }

            // Find keys to drop
            foreach ($old_keys as $key_id => $attr) {
                if (!isset($new_keys[$key_id])) {
                    $changes['keys_to_drop'][] = $attr;
                }
            }

            $table_changed = !empty($changes['columns_to_add']) || 
                           !empty($changes['columns_to_modify']) || 
                           !empty($changes['keys_to_add']) || 
                           !empty($changes['keys_to_drop']) || 
                           !empty($changes['keys_to_modify']);
        }

        // Generate CREATE TABLE query
        $sql = '';
        $columns = [];
        $table_keys = [];

        // check if table exists
        $table_exists = self::$db->selectrow_array("SHOW TABLES LIKE '{$metadata['table']->name}'")[0] ?? false;
        $alter_table = $table_exists && $table_changed;

        if ($alter_table) {
            $sql = "ALTER TABLE `{$metadata['table']->name}`\n  ";
        } else if(!$table_exists) {
           $sql = "CREATE TABLE IF NOT EXISTS `{$metadata['table']->name}` (\n";
        } else {
            return; // no changes needed
        }

        foreach ($metadata['columns'] as $column_name => $column_meta) {
            foreach ($column_meta['attributes'] as $attr) {
                if ($attr instanceof Model\Column) {
                    if ($alter_table) {
                        $column_exists = self::$db->selectrow_array("SHOW COLUMNS FROM `{$metadata['table']->name}` LIKE '{$column_name}'")[0] ?? false;
                        
                        if (in_array($column_name, $changes['columns_to_add']) && !$column_exists) {
                            $columns[] = $attr->to_add_sql();
                        } else if (in_array($column_name, $changes['columns_to_modify'])) {
                            $columns[] = $attr->to_modify_sql();
                        }
                    } else {
                        $columns[] = $attr->to_sql();
                    }
                } else if ($attr instanceof Model\PrimaryKey || $attr instanceof Model\ForeignKey || $attr instanceof Model\Index) {
                    if (!$alter_table) {
                        $table_keys[] = $attr->to_sql();
                    }
                }
            }
        }

        if ($alter_table) {
            foreach ($changes['keys_to_drop'] as $attr) {
                $table_keys[] = $attr->to_drop_sql();
            }
            var_dump($changes['keys_to_modify']);
            foreach ($changes['keys_to_modify'] as $mod) {
                $table_keys[] = $mod->to_modify_sql();
            }
            foreach ($changes['keys_to_add'] as $attr) {
                $table_keys[] = $attr->to_add_sql();
            }
        }
        
        if (!empty($columns)) {
            $sql .= implode(",\n  ", $columns);
        }

        if (!empty($table_keys)) {
            if (!empty($columns)) {
                $sql .= ",\n";
            }
            $sql .= implode(",\n  ", $table_keys);
        }
        if (!$alter_table) {
            $sql .= "\n) " . $metadata['table']->to_sql();
        }

        echo $sql . "\n";
        
        if ($test_run) {
            
            return;
        }

        $rslt = self::$db->query($sql);

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