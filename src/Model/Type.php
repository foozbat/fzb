<?php
/**
 * Enum Type
 * 
 * Defines MySQL column data types for use in Column attributes.
 * Includes numeric, string, date/time, and JSON types.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

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
    case BOOLEAN = 'boolean';
    
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