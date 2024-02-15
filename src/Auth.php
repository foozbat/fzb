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
    public ?User $user = null;
    public ?UserSession $user_session = null;

    public bool $is_authenticated = false;
    public bool $login_required = false;

    private static $instance = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        // I'm a singleton
        if (self::$instance !== null) {
            throw new AuthException("An auth object has already been instantiated.  Cannot create more than one instance.");
        } else {
            self::$instance = $this;
        }

        $this->cookie_name = (defined('APP_NAME') ? APP_NAME : 'fzb_app') . '_auth_token';

        if (isset($_COOKIE[ $this->cookie_name ])) {
            $this->user_session = UserSession::get_by(token: $_COOKIE[ $this->cookie_name ]);
        }
        
         if ($this->user_session !== null) {
            $this->user = User::get_by(id: $this->user_session->user_id);

            if ($this->user !== null) {
                $this->is_authenticated = true;
            }
        }
    }

    
    /**
     * interface for retrieving the Config singleton
     *
     * @return Auth Auth instance
     */
    public static function get_instance(): Auth
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

        if ($user === null) {
            return false;
        }

        if (password_verify($password, $user->password)) { // change to strong encryption
            $this->user = $user;
            $this->user_session = new UserSession(user_id: $user->id);
            $this->user_session->save();
            $this->is_authenticated = true;

            setcookie($this->cookie_name, $this->user_session->token, time() + 3600, '/');
            return true;
        }

        return false;
    }

    public function logout(): bool
    {
        setcookie($this->cookie_name, "token", time() - 3600);
        if ($this->user_session !== null) {
            $this->user_session->delete();
        }

        return true;
    }

    public function login_required()
    {

    }
}