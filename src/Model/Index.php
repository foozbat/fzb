<?php

declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Index
{
    public function __construct(
        public readonly ?IndexType $type = null,
        public readonly ?string $name = null,
        public readonly ?int $length = null,
        public readonly ?IndexAlgorithm $algorithm = null,
        public readonly ?string $comment = null
    ) {}

    public function toSQL(string $column_name): string
    {
        $index_name = $this->name ?? "idx_{$column_name}";
        
        $sql = "";
        if ($this->type !== null) {
            $sql .= $this->type->value . " ";
        }
        
        $sql .= "INDEX `{$index_name}` (`{$column_name}`";
        
        if ($this->length !== null) {
            $sql .= "({$this->length})";
        }
        
        $sql .= ")";
        
        if ($this->algorithm !== null) {
            $sql .= " USING {$this->algorithm->value}";
        }
        
        if ($this->comment !== null) {
            $sql .= " COMMENT '" . addslashes($this->comment) . "'";
        }
        
        return $sql;
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