<?php

declare(strict_types=1);

namespace Fzb;

use Exception;

class UserException extends Exception { }

class User extends Model
{
    const __table__ = 'users';

    public string $username;
    public string $password;


}