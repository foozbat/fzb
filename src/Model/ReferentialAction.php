<?php
/**
 * Enum ReferentialAction
 * 
 * Defines MySQL foreign key referential actions for ON DELETE and ON UPDATE clauses.
 * Used in ForeignKey attributes to specify cascading behavior.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

enum ReferentialAction: string {
    case RESTRICT = 'RESTRICT';
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
    case SET_DEFAULT = 'SET DEFAULT';
}