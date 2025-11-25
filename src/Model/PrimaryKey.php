<?php
declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey extends ModelAttribute
{
    public function __construct(
        public readonly ?string $table_name = null,
        public readonly ?string $column_name = null
    ) {}

    public function to_sql(): string
    {
        return "PRIMARY KEY (`{$this->column_name}`)";
    }

    public function to_add_sql(): string
    {
        return "ADD " . $this->to_sql();
    }

    public function to_modify_sql(): string
    {
        return $this->to_drop_sql() . ",\n  " . $this->to_add_sql();
    }

    public function to_drop_sql(): string
    {
        return "DROP PRIMARY KEY";
    }

}