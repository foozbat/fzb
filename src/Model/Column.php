<?php
/**
 * Class Column
 * 
 * Attribute class for defining column properties.
 * Applied to model properties to specify column type, constraints, defaults, and behavior.
 * 
 * Usage: #[Column(type: Type::VARCHAR, length: 255, null: false)]
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column extends ModelAttribute
{
    public function __construct(
        public readonly Type $type,
        public readonly ?int $length = null,
        public readonly bool $null = true,
        public readonly bool $unsigned = false,
        public readonly bool $auto_increment = false,
        public Time|string|int|float|bool|null $default = null,
        public readonly ?Time $on_update = null,
        public readonly ?string $table_name = null,
        public readonly ?string $column_name = null
    ) {}

    /**
     * Generates column definition SQL for CREATE TABLE
     *
     * @return string column definition with type, constraints, and defaults
     */
    public function to_sql(): string
    {
        $type = strtoupper($this->type->value);
        if ($this->length && in_array($this->type, [Type::VARCHAR, Type::CHAR, Type::BINARY, Type::VARBINARY, Type::BIT, Type::TINYINT, Type::SMALLINT, Type::MEDIUMINT, Type::INT, Type::BIGINT, Type::DECIMAL, Type::FLOAT, Type::DOUBLE])) {
            $type .= "({$this->length})";
        }

        $sql = "`{$this->column_name}` {$type}";

        if ($this->unsigned && in_array($this->type, [Type::TINYINT, Type::SMALLINT, Type::MEDIUMINT, Type::INT, Type::BIGINT, Type::DECIMAL, Type::FLOAT, Type::DOUBLE]))
            $sql .= " UNSIGNED";
        if (!$this->null)
            $sql .= " NOT NULL";
        if ($this->auto_increment)
            $sql .= " AUTO_INCREMENT";
        if ($this->default !== null) {
            if ($this->default instanceof Time) {
                $sql .= " DEFAULT {$this->default->value}";
            } else {
                $sql .= " DEFAULT " . (is_string($this->default) && !in_array(strtoupper($this->default), ['CURRENT_TIMESTAMP', 'NOW()', 'NULL']) ? "'" . addslashes($this->default) . "'" : (is_bool($this->default) ? ($this->default ? '1' : '0') : $this->default));
            }
        }
        if ($this->on_update !== null)
            $sql .= " ON UPDATE {$this->on_update->value}";

        return $sql;
    }

    /**
     * Generates ADD COLUMN SQL for ALTER TABLE
     *
     * @return string ADD COLUMN statement
     */
    public function to_add_sql(): string
    {
        return "ADD COLUMN " . $this->to_sql();
    }

    /**
     * Generates MODIFY COLUMN SQL for ALTER TABLE
     *
     * @return string MODIFY COLUMN statement
     */
    public function to_modify_sql(): string
    {
        return "MODIFY COLUMN " . $this->to_sql();
    }
}