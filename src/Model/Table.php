<?php
/**
 * Class Table
 * 
 * Attribute class for defining table-level properties.
 * Applied to model classes to specify table name, storage engine, charset, and collation.
 * 
 * Usage: #[Table(name: 'users', engine: Engine::INNODB)]
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table extends ModelAttribute
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?Engine $engine = Engine::INNODB,
        public readonly ?Charset $charset = Charset::UTF8MB4,
        public readonly ?Collation $collation = Collation::UTF8MB4_UNICODE_CI
    ) {}

    /**
     * Generates table options SQL fragment
     *
     * @return string ENGINE, CHARSET, and COLLATE clauses
     */
    public function to_sql(): string
    {
        $options = "";
        
        if ($this->engine !== null) {
            $options .= "ENGINE={$this->engine->value}";
        }
        
        if ($this->charset !== null) {
            $options .= " DEFAULT CHARSET={$this->charset->value}";
        }
        
        if ($this->collation !== null) {
            $options .= " COLLATE={$this->collation->value}";
        }
        
        return trim($options);
    }
}