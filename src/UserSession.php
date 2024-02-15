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
        $params['token'] = base64_encode(random_bytes(32));

        parent::__construct(...$params);
    }
}