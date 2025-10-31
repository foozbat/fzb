<?php

declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public readonly Type $type,
        public readonly ?int $length = null,
        public readonly bool $null = true,
        public readonly bool $unsigned = false,
        public readonly bool $auto_increment = false,
        public mixed $default = null
    ) {}

    public function toSQL(string $name): string
    {
        $type = strtoupper($this->type->value);
        if ($this->length && in_array($this->type, [Type::VARCHAR, Type::CHAR, Type::BINARY, Type::VARBINARY, Type::BIT, Type::TINYINT, Type::SMALLINT, Type::MEDIUMINT, Type::INT, Type::BIGINT, Type::DECIMAL, Type::FLOAT, Type::DOUBLE])) {
            $type .= "({$this->length})";
        }
        
        $sql = "`{$name}` {$type}";

        if ($this->unsigned && in_array($this->type, [Type::TINYINT, Type::SMALLINT, Type::MEDIUMINT, Type::INT, Type::BIGINT, Type::DECIMAL, Type::FLOAT, Type::DOUBLE]))
            $sql .= " UNSIGNED";
        if (!$this->null)
            $sql .= " NOT NULL";
        if ($this->auto_increment)
            $sql .= " AUTO_INCREMENT";
        if ($this->default !== null)
            $sql .= " DEFAULT " . (is_string($this->default) && !in_array(strtoupper($this->default), ['CURRENT_TIMESTAMP', 'NOW()', 'NULL']) ? "'" . addslashes($this->default) . "'" : (is_bool($this->default) ? ($this->default ? '1' : '0') : $this->default));

        return $sql;
    }
}

enum Type: string {
    // Numeric types
    case TINYINT = 'tinyint';
    case SMALLINT = 'smallint';
    case MEDIUMINT = 'mediumint';
    case INT = 'int';
    case BIGINT = 'bigint';
    case DECIMAL = 'decimal';
    case FLOAT = 'float';
    case DOUBLE = 'double';
    case BIT = 'bit';
    
    // String types
    case CHAR = 'char';
    case VARCHAR = 'varchar';
    case BINARY = 'binary';
    case VARBINARY = 'varbinary';
    case TINYBLOB = 'tinyblob';
    case BLOB = 'blob';
    case MEDIUMBLOB = 'mediumblob';
    case LONGBLOB = 'longblob';
    case TINYTEXT = 'tinytext';
    case TEXT = 'text';
    case MEDIUMTEXT = 'mediumtext';
    case LONGTEXT = 'longtext';
    case ENUM = 'enum';
    case SET = 'set';
    
    // Date and time types
    case DATE = 'date';
    case TIME = 'time';
    case DATETIME = 'datetime';
    case TIMESTAMP = 'timestamp';
    case YEAR = 'year';
    
    // JSON type
    case JSON = 'json';
}