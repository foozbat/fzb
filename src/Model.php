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
    private static array $metadata = [];
    protected int $__iter__ = 0;

    public function __construct()
    {
        self::init();
    }

    private static function init(): string
    {
        $cls = get_called_class();

        // load metadata if exists
        if (defined('ORM_CACHE_DIR')) {
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
                        self::$metadata[$cls]['columns'][$property_name] = [];
                    }

                    array_push(self::$metadata[$cls]['columns'][$property_name], $attr->newInstance());
                }
            }
        }
        
        var_dump(self::$metadata[$cls]);

        // save the computed orm-data to file if enabled
        if (defined('ORM_CACHE_DIR')) {
            $output_filename = ORM_CACHE_DIR . '/' . str_replace('\\', '_', $cls) . '.php';
            $output_code = "<?php\nreturn " . var_export(self::$metadata[$cls], true) . ";\n";
            file_put_contents($output_filename, $output_code);
        }

        return $cls;
    }

    // ai generated slop, fix as needed
    public static function migrate(): void
    {
        $cls = self::init();
        $metadata = self::$metadata[$cls];
        $table = $metadata['table'];
        
        // Generate CREATE TABLE query
        $columns = [];
        $foreign_keys = [];
        $indexes = [];

        foreach ($metadata['columns'] as $column_name => $attributes) {
            foreach ($attributes as $attr) {
                if ($attr instanceof Model\Column) {
                    $columns[] = $attr->toSQL($column_name);
                } else if ($attr instanceof Model\ForeignKey) {
                    $foreign_keys[] = $attr->toSQL($column_name);
                } else if ($attr instanceof Model\Index) {
                    $indexes[] = $attr->toSQL($column_name);
                }
            }
        }

        $sql = "CREATE TABLE `{$table->name}` (\n  " . implode(",\n  ", $columns);
        if (!empty($foreign_keys)) {
            $sql .= ",\n  " . implode(",\n  ", $foreign_keys);
        }
        if (!empty($indexes)) {
            $sql .= ",\n  " . implode(",\n  ", $indexes);
        }
        $sql .= "\n) " . $table->getTableOptions();
        
        echo $sql . "\n";
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