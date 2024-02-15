<?php

declare(strict_types=1);

namespace Fzb;

use Exception;

class UserSessionException extends Exception { }

class UserSession extends Model
{
    const __table__ = 'user_sessions';


}