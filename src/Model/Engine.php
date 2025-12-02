<?php
/**
 * Enum Engine
 * 
 * Defines MySQL storage engines for use in Table attributes.
 * Includes InnoDB, MyISAM, and other common MySQL engines.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

enum Engine: string {
    case INNODB = 'InnoDB';
    case MYISAM = 'MyISAM';
    case MEMORY = 'MEMORY';
    case ARCHIVE = 'ARCHIVE';
    case CSV = 'CSV';
    case FEDERATED = 'FEDERATED';
    case MERGE = 'MERGE';
    case NDB = 'NDB';
    case BLACKHOLE = 'BLACKHOLE';
}