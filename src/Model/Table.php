<?php

declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly string $engine = 'InnoDB',
        public readonly string $charset = 'utf8mb4',
        public readonly string $collation = 'utf8mb4_unicode_ci'
    ) {}
    
    public function getTableOptions(): string
    {
        return "ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation}";
    }
}