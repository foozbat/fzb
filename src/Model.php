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

    public function __construct()
    {
        self::init();
    }

    private static function init(bool $no_cache=false): string
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

                        self::$metadata[$cls]['columns'][$property_name] = [];
                    }

                    array_push(self::$metadata[$cls]['columns'][$property_name], $attr->newInstance());
                }
            }
        }
        
        var_dump(self::$metadata[$cls]);

        return $cls;
    }

    // ai generated slop, fix as needed
    public static function migrate(): void
    {
        $cls = self::init(no_cache: true);

        $metadata = self::$metadata[$cls];
        
        // Generate CREATE TABLE query
        $SQL = '';
        $columns = [];
        $table_keys = [];

        // check if table exists
        $table_exists = self::$db->selectrow_array("SHOW TABLES LIKE '{$metadata['table']->name}'");
        $existing_table_columns = [];
        if (!$table_exists) {
            $sql = "CREATE TABLE IF NOT EXISTS `{$metadata['table']->name}` (\n";
        } else {
            $sql = "ALTER TABLE `{$metadata['table']->name}` (\n  ";
            $existing_table_columns = self::$db->get_column_names($metadata['table']->name);
        }

        foreach ($metadata['columns'] as $column_name => $attributes) {
            foreach ($attributes as $attr) {
                if ($attr instanceof Model\Column) {
                    $columns[] = ($table_exists ? (in_array($column_name, $existing_table_columns) ? 'CHANGE COLUMN' : 'ADD COLUMN') : '') . $attr->to_sql($column_name);
                } else if ($attr instanceof Model\PrimaryKey || $attr instanceof Model\ForeignKey || $attr instanceof Model\Index) {
                    // check if key exists

                    $table_keys[] = $attr->to_sql($column_name);
                }
            }
        }

        $sql .= implode(",\n  ", $columns);

        if (!empty($table_keys)) {
            $sql .= ",\n  " . implode(",\n  ", $table_keys);
        }
        $sql .= "\n) " . $metadata['table']->to_sql();

        echo $sql . "\n";

        $rslt = self::$db->query($sql);

        // if successful,save the computed orm-data to file if enabled
        if (defined('ORM_CACHE_DIR')) {
            $output_filename = ORM_CACHE_DIR . '/' . str_replace('\\', '_', $cls) . '.php';
            $output_code = "<?php\nreturn " . var_export(self::$metadata[$cls], true) . ";\n";
            file_put_contents($output_filename, $output_code);
        }
    }

    public function load(): bool
    {
        $cls = get_called_class();
        $metadata = self::$metadata[$cls];

        // Implement data loading logic here, e.g., from a database
        // This is a placeholder implementation
        return true;
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