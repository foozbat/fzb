<?php

declare(strict_types=1);

namespace Fzb;

use Exception;

class UserSessionException extends Exception { }

class UserSession extends Model
{
    const __table__ = 'user_sessions';

    public int $user_id;
    public string $token;

    public function __construct(...$params)
    {
        $params['token'] = $this->generate_uuid();

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

    private function generate_fingerprint() {
        $fingerprint = '';
    
        $fingerprint .= isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $fingerprint .= isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $fingerprint .= isset($_SERVER['HTTP_SCREEN_RESOLUTION']) ? $_SERVER['HTTP_SCREEN_RESOLUTION'] : '';
        $fingerprint .= isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        $fingerprint .= isset($_COOKIE) ? 'cookies:enabled' : 'cookies:disabled';
        $fingerprint .= isset($_SERVER['HTTP_WEBGL_VENDOR']) ? $_SERVER['HTTP_WEBGL_VENDOR'] : '';
        $fingerprint .= isset($_SERVER['HTTP_WEBGL_RENDERER']) ? $_SERVER['HTTP_WEBGL_RENDERER'] : '';
    
        return md5($fingerprint);
    }
}
