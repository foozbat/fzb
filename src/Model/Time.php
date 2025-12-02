<?php
/**
 * Enum Time
 * 
 * Defines MySQL time functions for use in Column default and on_update attributes.
 * Used for automatic timestamp values.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

enum Time: string {
    case CURRENT_TIMESTAMP = 'CURRENT_TIMESTAMP';
    case NOW = 'NOW()';
}