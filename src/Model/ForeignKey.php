<?php

declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ForeignKey extends ModelAttribute
{
    public function __construct(
        public readonly string $references,
        public readonly string $reference_column,
        public readonly ?ReferentialAction $on_delete = null,
        public readonly ?ReferentialAction $on_update = null,
        public readonly ?string $table_name = null,
        public readonly ?string $column_name = null
    ) {}

    private function index_sql(): string
    {
        return "INDEX `idx_{$this->column_name}` (`{$this->column_name}`)";
    }

    private function constraint_sql(): string
    {
        $sql = "CONSTRAINT `fk_{$this->column_name}` FOREIGN KEY (`{$this->column_name}`) REFERENCES `{$this->references}`(`{$this->reference_column}`)";

        if ($this->on_delete !== null)
            $sql .= " ON DELETE {$this->on_delete->value}";
        if ($this->on_update !== null)
            $sql .= " ON UPDATE {$this->on_update->value}";
            
        return $sql;
    }

    public function to_sql(): string
    {
        return $this->index_sql() . ",\n  " . $this->constraint_sql();
    }

    public function to_add_index_sql(): string
    {
        return "ADD " . $this->index_sql();
    }

    public function to_add_fk_sql(): string
    {
        $sql = "ADD " . $this->constraint_sql();
            
        return $sql;
    }

    public function to_drop_fk_sql(): string
    {
        return "DROP FOREIGN KEY `fk_{$this->column_name}`";
    }

    public function to_drop_index_sql(): string
    {
        return "DROP INDEX `idx_{$this->column_name}`";
    }

}

enum ReferentialAction: string {
    case RESTRICT = 'RESTRICT';
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
    case SET_DEFAULT = 'SET DEFAULT';
}