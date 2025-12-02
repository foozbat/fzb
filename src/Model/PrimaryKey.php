<?php
/**
 * Class PrimaryKey
 * 
 * Attribute class for marking a column as the primary key.
 * Applied to model properties to designate them as the table's primary key.
 * 
 * Usage: #[PrimaryKey]
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

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

    /**
     * Generates PRIMARY KEY constraint SQL for CREATE TABLE
     *
     * @return string PRIMARY KEY constraint definition
     */
    public function to_sql(): string
    {
        return "PRIMARY KEY (`{$this->column_name}`)";
    }

    /**
     * Generates ADD PRIMARY KEY SQL for ALTER TABLE
     *
     * @return string ADD PRIMARY KEY statement
     */
    public function to_add_sql(): string
    {
        return "ADD " . $this->to_sql();
    }

    /**
     * Generates PRIMARY KEY modification SQL for ALTER TABLE
     *
     * @return string DROP and ADD PRIMARY KEY statements
     */
    public function to_modify_sql(): string
    {
        return $this->to_drop_sql() . ",\n  " . $this->to_add_sql();
    }

    /**
     * Generates DROP PRIMARY KEY SQL for ALTER TABLE
     *
     * @return string DROP PRIMARY KEY statement
     */
    public function to_drop_sql(): string
    {
        return "DROP PRIMARY KEY";
    }

}