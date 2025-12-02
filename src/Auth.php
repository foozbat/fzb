<?php
/**
 * Class Auth
 * 
 * Singleton authentication manager handling user login, logout, and session management.
 * Manages authentication tokens via cookies and validates CSRF tokens for protected operations.
 * Supports custom User and UserSession classes for flexible authentication implementations.
 * 
 * Usage: $auth = new Fzb\Auth(); or $auth = Fzb\Auth::get_instance();
 * 
 * @author Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb;

use Exception;

class AuthException extends Exception { }

class Auth
{
    /**
     * @var User|null Currently authenticated user object
     */
    public ?User $user = null;
    
    /**
     * @var UserSession|null Current user session object
     */
    public ?UserSession $user_session = null;

    /**
     * @var bool True if user is authenticated
     */
    public bool $is_authenticated = false;
    
    /**
     * @var bool Flag for requiring login
     */
    public bool $login_required = false;
    
    /**
     * @var bool True if CSRF token was validated
     */
    public bool $csrf_validated = false;

    /**
     * @var string Fully qualified class name for User model
     */
    private static string $user_cls;
    
    /**
     * @var string Fully qualified class name for UserSession model
     */
    private static string $user_session_cls;

    /**
     * @var string Cookie name for authentication token
     */
    private static string $auth_token_name = (APP_NAME ?? 'fzb_app') . '_auth_token';
    
    /**
     * @var string Cookie/POST name for CSRF token
     */
    private static string $csrf_token_name = (APP_NAME ?? 'fzb_app') . '_csrf_token';

    /**
     * @var Auth|null Singleton instance
     */
    private static $instance = null;

    /**
     * @var callable|null Callback function for authentication failures
     */
    private $fail_callback;

    /**
     * Constructor - initializes authentication from cookies and validates existing sessions
     *
     * @param string $user_cls Fully qualified class name for User model (default: Fzb\User)
     * @param string $user_session_cls Fully qualified class name for UserSession model (default: Fzb\UserSession)
     * @throws AuthException if Auth instance already exists (singleton)
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

    /**
     * Sets callback function to execute when authentication or CSRF validation fails
     *
     * @param callable $callback Callback function to execute on failure
     * @return void
     * @throws AuthException if callback is not callable
     */
    public function on_failure(callable $callback)
    {
        if (!is_callable($callback)) {
            throw new AuthException("Provide a valid callback for on_failure()");
        }

        $this->fail_callback = $callback;
    }

    /**
     * Enforces authentication requirement - calls failure callback if user not authenticated
     *
     * @return void
     */
    public function login_required()
    {
        if (!$this->is_authenticated) {
            call_user_func($this->fail_callback, $_SERVER['REQUEST_URI']);
        }
    }

    /**
     * Enforces CSRF token validation - calls failure callback if token invalid
     *
     * @return void
     */
    public function csrf_required()
    {
        if (!$this->csrf_validated) {
            call_user_func($this->fail_callback);
        }
    }
}