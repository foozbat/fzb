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
    public bool $csrf_validated = false;

    private static string $user_cls;
    private static string $user_session_cls;

    private static string $auth_token_name = (APP_NAME ?? 'fzb_app') . '_auth_token';
    private static string $csrf_token_name = (APP_NAME ?? 'fzb_app') . '_csrf_token';

    private static $instance = null;

    private $fail_callback;

    /**
     * Constructor
     */
    public function __construct(string $user_cls = User::class, string $user_session_cls = UserSession::class)
    {
        // I'm a singleton
        if (self::$instance !== null) {
            throw new AuthException("An auth object has already been instantiated.  Cannot create more than one instance.");
        } else {
            self::$instance = $this;
        }

        self::$user_cls = $user_cls;
        self::$user_session_cls = $user_session_cls;

        // try to get session from auth token cookie
        if (isset($_COOKIE[self::$auth_token_name])) {
            $this->user_session = self::$user_session_cls::get_by(auth_token: $_COOKIE[self::$auth_token_name], logged_in: true);
        } else {
            // set an unauthenticated token to track this un-logged in user
            $this->set_auth_cookie(self::$user_session_cls::generate_uuid(), time() + 60*60*24*30);
        }
        
        // try to authenticate auth token
        if ($this->user_session !== null) {
            $this->user = self::$user_cls::get_by(id: $this->user_session->user_id);

            if ($this->user !== null && $this->user_session->validate_fingerprint()) {
                $this->is_authenticated = true;
            }

            $csrf_token = $_POST[self::$csrf_token_name] ?? $_GET[self::$csrf_token_name] ?? null;

            if ($this->user !== null && $this->user_session->csrf_token == $csrf_token) {
                $this->csrf_validated = true;
            }
        }
    }

    
    /**
     * interface for retrieving the Auth singleton
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

    /**
     * Attempts to log in a user via username/password.  
     * If authenticated, creates a new user session and sets the session token cookie.
     *
     * @param string $username username
     * @param string $password password
     * @return boolean login success/failure
     */
    public function login(string $username, string $password): bool
    {
        if (!$username || !$password) {
            return false;
        }

        $user = self::$user_cls::get_by(username: $username);

        if ($user === null) {
            return false;
        }

        if (password_verify($password, $user->password)) {
            $this->user = $user;

            // invalidate old session if exists
            if ($this->user_session !== null) {
                $this->user_session->logged_in = false;
                $this->user_session->save();
            }

            // create a new session
            $this->user_session = new self::$user_session_cls(user_id: $user->id);
            $this->user_session->logged_in = true; 
            $this->user_session->save();
            $this->is_authenticated = true;

            $this->set_auth_cookie($this->user_session->auth_token, time() + 60*60*24*30); // change expire time

            return true;
        }

        return false;
    }

    /**
     * Logs a user out.  Invalidates the user session and deletes the auth token cookie.
     *
     * @return boolean Logout success.
     */
    public function logout(): bool
    {
        // set new unauthenticated token
        $this->set_auth_cookie(self::$user_session_cls::generate_uuid(), time() + 60*60*24*30);

        if ($this->user_session !== null) {
            $this->user_session->logged_in = false;
            $this->user_session->save();
        }

        return true;
    }

    /**
     * Helper function to set a cookie with the auth token
     *
     * @param string $token Auth token
     * @param integer|boolean $expires time to expire
     * @return void
     */
    private function set_auth_cookie(string $token, int|bool $expires): void
    {
        $cookie_options = array (
            'expires' => $expires, 
            'path' => '/', 
            //'domain' => '.foozbat.net', // leading dot for compatibility or use subdomain
            'secure' => false,     // or false
            'httponly' => true,    // or false
            'samesite' => 'Strict' // None || Lax  || Strict
        );

        setcookie(self::$auth_token_name, $token, $cookie_options);
    }

    public function on_failure(callable $callback)
    {
        if (!is_callable($callback)) {
            throw new AuthException("Provide a valid callback for on_failure()");
        }

        $this->fail_callback = $callback;
    }

    public function login_required()
    {
        if (!$this->is_authenticated) {
            call_user_func($this->fail_callback, $_SERVER['REQUEST_URI']);
        }
    }

    public function csrf_required()
    {
        if (!$this->csrf_validated) {
            call_user_func($this->fail_callback);
        }
    }
}