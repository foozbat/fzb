<?php
declare(strict_types=1);

namespace Fzb\Model;

class ModelAttribute
{
    public static function __set_state(array $properties)
    {
        $cls = get_called_class();
        $obj = new $cls(...$properties);
        return $obj;
    }
}