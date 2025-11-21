<?php

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

enum Charset: string {
    case UTF8MB4 = 'utf8mb4';
    case UTF8 = 'utf8';
    case LATIN1 = 'latin1';
    case ASCII = 'ascii';
    case BINARY = 'binary';
    case UTF16 = 'utf16';
    case UTF32 = 'utf32';
    case BIG5 = 'big5';
    case GB2312 = 'gb2312';
    case GBK = 'gbk';
    case SJIS = 'sjis';
    case EUC_JP = 'eucjpms';
    case KOI8R = 'koi8r';
    case KOI8U = 'koi8u';
    case TIS620 = 'tis620';
}

enum Collation: string {
    // UTF8MB4 collations
    case UTF8MB4_UNICODE_CI = 'utf8mb4_unicode_ci';
    case UTF8MB4_GENERAL_CI = 'utf8mb4_general_ci';
    case UTF8MB4_BIN = 'utf8mb4_bin';
    case UTF8MB4_0900_AI_CI = 'utf8mb4_0900_ai_ci';
    case UTF8MB4_0900_AS_CS = 'utf8mb4_0900_as_cs';
    case UTF8MB4_0900_BIN = 'utf8mb4_0900_bin';
    
    // UTF8 collations
    case UTF8_UNICODE_CI = 'utf8_unicode_ci';
    case UTF8_GENERAL_CI = 'utf8_general_ci';
    case UTF8_BIN = 'utf8_bin';
    
    // Latin1 collations
    case LATIN1_SWEDISH_CI = 'latin1_swedish_ci';
    case LATIN1_GENERAL_CI = 'latin1_general_ci';
    case LATIN1_BIN = 'latin1_bin';
    
    // ASCII collations
    case ASCII_GENERAL_CI = 'ascii_general_ci';
    case ASCII_BIN = 'ascii_bin';
    
    // Binary
    case BINARY = 'binary';
}