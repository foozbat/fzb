<?php
declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey {
    public function to_sql(string $name): string
    {
        return "PRIMARY KEY (`{$name}`)";
    }
}