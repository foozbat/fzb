<?php
/**
 * Class ForeignKey
 * 
 * Attribute class for defining foreign key relationships.
 * Applied to model properties to create foreign key constraints with referential actions.
 * 
 * Usage: #[ForeignKey(references: 'users', reference_column: 'id', on_delete: ReferentialAction::CASCADE)]
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

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

    /**
     * Generates index SQL for foreign key column
     *
     * @return string INDEX definition for foreign key column
     */
    private function index_sql(): string
    {
        return "INDEX `idx_{$this->column_name}` (`{$this->column_name}`)";
    }

    /**
     * Generates foreign key constraint SQL
     *
     * @return string FOREIGN KEY constraint with ON DELETE/UPDATE clauses
     */
    private function constraint_sql(): string
    {
        $sql = "CONSTRAINT `fk_{$this->column_name}` FOREIGN KEY (`{$this->column_name}`) REFERENCES `{$this->references}`(`{$this->reference_column}`)";

        if ($this->on_delete !== null)
            $sql .= " ON DELETE {$this->on_delete->value}";
        if ($this->on_update !== null)
            $sql .= " ON UPDATE {$this->on_update->value}";
            
        return $sql;
    }

    /**
     * Generates complete foreign key SQL for CREATE TABLE
     *
     * @return string INDEX and FOREIGN KEY constraint definitions
     */
    public function to_sql(): string
    {
        return $this->index_sql() . ",\n  " . $this->constraint_sql();
    }

    /**
     * Generates ADD INDEX SQL for ALTER TABLE
     *
     * @return string ADD INDEX statement for foreign key column
     */
    public function to_add_index_sql(): string
    {
        return "ADD " . $this->index_sql();
    }

    /**
     * Generates ADD FOREIGN KEY constraint SQL for ALTER TABLE
     *
     * @return string ADD CONSTRAINT FOREIGN KEY statement
     */
    public function to_add_fk_sql(): string
    {
        $sql = "ADD " . $this->constraint_sql();
            
        return $sql;
    }

    /**
     * Generates DROP FOREIGN KEY SQL for ALTER TABLE
     *
     * @return string DROP FOREIGN KEY statement
     */
    public function to_drop_fk_sql(): string
    {
        return "DROP FOREIGN KEY `fk_{$this->column_name}`";
    }

    /**
     * Generates DROP INDEX SQL for ALTER TABLE
     *
     * @return string DROP INDEX statement for foreign key column
     */
    public function to_drop_index_sql(): string
    {
        return "DROP INDEX `idx_{$this->column_name}`";
    }

}