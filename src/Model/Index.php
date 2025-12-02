<?php
/**
 * Class Index
 * 
 * Attribute class for defining indexes on columns.
 * Supports UNIQUE, FULLTEXT, and SPATIAL indexes with optional algorithms.
 * 
 * Usage: #[Index(type: IndexType::UNIQUE, algorithm: IndexAlgorithm::BTREE)]
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

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

    /**
     * Generates index definition SQL for CREATE TABLE
     *
     * @return string index definition with type, algorithm, and comment
     */
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

    /**
     * Generates ADD INDEX SQL for ALTER TABLE
     *
     * @return string ADD INDEX statement
     */
    public function to_add_sql(): string
    {
        return "ADD " . $this->to_sql();
    }

    /**
     * Generates index modification SQL for ALTER TABLE
     *
     * @return string DROP and ADD INDEX statements
     */
    public function to_modify_sql(): string
    {
        return $this->to_drop_sql() . ",\n  " . $this->to_add_sql();
    }

    /**
     * Generates DROP INDEX SQL for ALTER TABLE
     *
     * @return string DROP INDEX statement
     */
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