<?php

declare(strict_types=1);

namespace Fzb;

use Exception;

class UserSessionException extends Exception { }

class UserSession extends Model
{
    const __table__ = 'user_sessions';

    public int $user_id;
    public bool $logged_in;
    public string $auth_token;
    public string $csrf_token;
    public string $fingerprint;

    public function __construct(...$params)
    {
        $this->auth_token = $this->generate_uuid();
        $this->csrf_token = $this->generate_uuid();
        $this->fingerprint = $this->generate_fingerprint();

        parent::__construct(...$params);
    }

    private function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function generate_fingerprint() {
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
    
        //echo $fingerprint."<br />";

        return md5($fingerprint);
    }

    public function validate_fingerprint() {
        //echo $this->fingerprint;
        return $this->fingerprint == $this->generate_fingerprint();
    }
}