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

    public function __construct(...$params)
    {
        // hash password if it's not already hashed
        if (isset($params['password'])) {
            if (password_get_info($params['password'])['algoName'] == 'unknown') {
                $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
            }
        }

        parent::__construct(...$params);
    }

    public function change_password(string $old_pass, string $new_pass): bool
    {
        if (!$this->verify_password($old_pass)) {
            return false;
        }

        $this->password = password_hash($new_pass, PASSWORD_DEFAULT);

        return true;
    }

    public function verify_password(string $password): bool
    {
        return password_verify($password, $this->password);
    }

}