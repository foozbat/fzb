<?php
/**
 * Class Auth
 * 
 * @todo implement class
 */

declare(strict_types=1);

namespace Fzb;

use Fzb;
use Exception;

class AuthException extends Exception { }

class Auth
{
    private ?User $user = null;
    private ?UserSession $user_session = null;

    private string $cookie_prefix = 'FZB_APP';
    private bool $authenticated = false;

    private static $instance = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        echo "construct <br />";
        // I'm a singleton
        if (self::$instance !== null) {
            throw new AuthException("An auth object has already been instantiated.  Cannot create more than one instance.");
        } else {
            self::$instance = $this;
        }

        if (defined('APP_NAME')) {
            $this->cookie_prefix = APP_NAME;
        }

        if (isset($_COOKIE[ $this->cookie_prefix . '_auth_token' ])) {
            $this->user_session = UserSession::get_by(token: $token);
        }
        
         if ($this->user_session !== null) {
            $this->user = User::get($this->user_session->user_id);

            if ($this->user !== null) {
                $this->authenticated = true;
            }
        }

        echo "done construct <br/>";
    }

    
    /**
     * interface for retrieving the Config singleton
     *
     * @return Auth Auth instance
     */
    public static function get_instance(): Config
    {
        if (self::$instance === null) {
            throw new AuthException("Auth instance could not be loaded.  Instantiate a new Auth object.");
        }

        return self::$instance;
    }

    public function get_user()
    {
        return $this->user;
    }

    public function login(string $username, string $password): bool
    {
        if (!$username || !$password) {
            return false;
        }

        $user = User::get_by(username: $username);

        var_dump($user);

        if ($user === null) {
            return false;
        }

        if ($user->password == $password) { // change to strong encryption
            $this->user = $user;
            $this->user_session = new UserSession();
            $this->authenticated = true;
            return true;
        }

        return false;
    }

    public function is_authenticated()
    {
        return $this->authenticated;
    }

    public function login_required()
    {

    }
}