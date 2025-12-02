<?php
/**
 * Class ModelAttribute
 * 
 * Base class for all model attribute classes.
 * Provides __set_state for proper var_export/serialization support in metadata caching.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

class ModelAttribute
{
    public function __construct() {}
    
    public static function __set_state(array $properties)
    {
        $cls = get_called_class();
        $obj = new $cls(...$properties);
        return $obj;
    }
}