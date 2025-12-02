<?php
/**
 * Enum Charset
 * 
 * Defines MySQL character sets for use in Table attributes.
 * Includes UTF-8, ASCII, Latin1, and various international character sets.
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb\Model;

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