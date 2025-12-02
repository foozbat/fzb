<?php
/**
 * Class User
 * 
 * Base user model providing username/password authentication.
 * Automatically hashes passwords using PHP's password_hash function.
 * Extends Base model to include id, created_at, and updated_at fields.
 * 
 * Typical usage is to extend this class to add custom user fields:
 *   class MyUser extends Fzb\User {
 *       #[Column(type: Type::VARCHAR, length: 100)]
 *       public string $my_column;
 *   }
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb;

use Fzb\Model\Base;
use Fzb\Model\Table;
use Fzb\Model\Column;
use Fzb\Model\Type;
use Exception;

class UserException extends Exception { }

#[Table('users')]
class User extends Base
{
    /**
     * @var string Username for authentication
     */
    #[Column(type: Type::VARCHAR, length: 255)]
    public string $username;

    /**
     * @var string Hashed password (bcrypt)
     */
    #[Column(type: Type::VARCHAR, length: 255)]
    public string $password;

    /**
     * Constructor - automatically hashes plaintext passwords
     *
     * @param mixed ...$params user data including username and password
     */
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

    /**
     * Changes user password after verifying old password
     *
     * @param string $old_pass current password for verification
     * @param string $new_pass new password to set (will be hashed)
     * @return bool true if password changed successfully, false if old password incorrect
     */
    public function change_password(string $old_pass, string $new_pass): bool
    {
        if (!$this->verify_password($old_pass)) {
            return false;
        }

        $this->password = password_hash($new_pass, PASSWORD_DEFAULT);

        return true;
    }

    /**
     * Verifies a plaintext password against stored hash
     *
     * @param string $password plaintext password to verify
     * @return bool true if password matches, false otherwise
     */
    public function verify_password(string $password): bool
    {
        return password_verify($password, $this->password);
    }

}