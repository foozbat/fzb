<?php
/**
 * Enum Collation
 * 
 * Defines MySQL collations for use in Table attributes.
 * Includes collations for UTF-8, Latin1, ASCII, and binary character sets.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

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