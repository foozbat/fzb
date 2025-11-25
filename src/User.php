<?php

declare(strict_types=1);

namespace Fzb;

use Model\Table;
use Model\Column;
use Model\Type;
use Exception;

class UserException extends Exception { }

#[Table('users')]
class User extends Model\Base
{
    #[Column(type: Type::VARCHAR, length: 255)]
    public string $username;

    #[Column(type: Type::VARCHAR, length: 255)]
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