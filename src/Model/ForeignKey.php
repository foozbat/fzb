<?php

declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ForeignKey
{
    public function __construct(
        public readonly string $references,
        public readonly string $reference_column,
        public readonly ?ReferentialAction $on_delete = null,
        public readonly ?ReferentialAction $on_update = null
    ) {}

    public function toSQL(string $name): string
    {
        $sql = "FOREIGN KEY (`{$name}`) REFERENCES `{$this->references}`(`{$this->reference_column}`)";
        
        if ($this->on_delete !== null)
            $sql .= " ON DELETE {$this->on_delete->value}";
        if ($this->on_update !== null)
            $sql .= " ON UPDATE {$this->on_update->value}";
            
        return $sql;
    }
}

enum ReferentialAction: string {
    case RESTRICT = 'RESTRICT';
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
    case SET_DEFAULT = 'SET DEFAULT';
}