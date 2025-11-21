<?php

declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Index extends ModelAttribute
{
    public function __construct(
        public readonly ?IndexType $type = null,
        public readonly ?int $length = null,
        public readonly ?IndexAlgorithm $algorithm = null,
        public readonly ?string $comment = null,
        public readonly ?string $table_name = null,
        public readonly ?string $column_name = null
    ) {}

    public function to_sql(): string
    {
        $sql = "";
        if ($this->type !== null)
            $sql .= $this->type->value . " ";

        $sql .= "INDEX `idx_{$this->column_name}` (`{$this->column_name}`";

        if ($this->length !== null)
            $sql .= "({$this->length})";
        
        $sql .= ")";
        
        if ($this->algorithm !== null)
            $sql .= " USING {$this->algorithm->value}";
        
        if ($this->comment !== null)
            $sql .= " COMMENT '" . addslashes($this->comment) . "'";
        
        return $sql;
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
        return "DROP INDEX `idx_{$this->column_name}`";
    }

}

enum IndexType: string {
    case UNIQUE = 'UNIQUE';
    case FULLTEXT = 'FULLTEXT';
    case SPATIAL = 'SPATIAL';
}

enum IndexAlgorithm: string {
    case BTREE = 'BTREE';
    case HASH = 'HASH';
}