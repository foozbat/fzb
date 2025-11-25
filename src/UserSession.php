<?php

declare(strict_types=1);

namespace Fzb;

use Fzb\Model\Base;
use Fzb\Model\Table;
use Fzb\Model\Column;
use Fzb\Model\Type;
use Exception;

class UserSessionException extends Exception { }

#[Table('user_sessions')]
class UserSession extends Base
{
    #[Column(type: Type::INT)]
    public int $user_id;

    #[Column(type: Type::BOOLEAN, default: true)]
    public bool $logged_in;

    #[Column(type: Type::VARCHAR, length: 255)]
    public string $auth_token;

    #[Column(type: Type::VARCHAR, length: 255)]
    public string $csrf_token;

    #[Column(type: Type::VARCHAR, length: 255)]
    public string $fingerprint;

    #[Column(type: Type::VARCHAR, length: 255, null: true)]
    public ?string $sms_2fa_code;

    #[Column(type: Type::VARCHAR, length: 255, null: true)]
    public ?string $sms_2fa_expires_at;

    public function __construct(...$params)
    {
        $this->auth_token = self::generate_uuid();
        $this->csrf_token = self::generate_uuid();
        $this->fingerprint = self::generate_fingerprint();

        parent::__construct(...$params);
    }

    public static function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }

    public static function generate_fingerprint() {
        $fingerprint = '';
    
        $fingerprint .= "useragent:";
        $fingerprint .= $_SERVER['HTTP_USER_AGENT'] ?? '';
        $fingerprint .= "encoding:";
        $fingerprint .= $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        $fingerprint .= "lang:";
        $fingerprint .= $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $fingerprint .= "dnt:";
        $fingerprint .= $_SERVER['HTTP_DNT'] ?? '';
        $fingerprint .= "cookie:";
        $fingerprint .= isset($_COOKIE) ? '1' : '';
    
        return md5($fingerprint);
    }

    public function validate_fingerprint() {
        return $this->fingerprint == self::generate_fingerprint();
    }
}