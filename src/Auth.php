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

    private string $user_cls;
    private string $user_session_cls;
    private string $auth_token_name;
    private string $csrf_token_name;

    private static $instance = null;

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

        $this->user_cls = $user_cls;
        $this->user_session_cls = $user_session_cls;
        $this->auth_token_name = (defined('APP_NAME') ? APP_NAME : 'fzb_app') . '_auth_token';
        $this->csrf_token_name = (defined('APP_NAME') ? APP_NAME : 'fzb_app') . '_csrf_token';

        if (isset($_COOKIE[ $this->auth_token_name ])) {
            $this->user_session = $user_session_cls::get_by(auth_token: $_COOKIE[ $this->auth_token_name ]);
        }
        
        if ($this->user_session !== null) {
            $this->user = $user_cls::get_by(id: $this->user_session->user_id);

            if ($this->user !== null && $this->user_session->logged_in && $this->user_session->validate_fingerprint()) {
                $this->is_authenticated = true;
            }

            $csrf_token = $_POST[$this->csrf_token_name] ?? $_GET[$this->csrf_token_name] ?? null;

            if ($this->user !== null && $this->user_session->csrf_token == $csrf_token) {
                $this->csrf_validated = true;
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

    public function login(string $username, string $password): bool
    {
        if (!$username || !$password) {
            return false;
        }

        $user = User::get_by(username: $username);

        if ($user === null) {
            return false;
        }

        if (password_verify($password, $user->password)) {
            $this->user = $user;
            $this->user_session = new $this->user_session_cls(user_id: $user->id);
            $this->user_session->save();
            $this->is_authenticated = true;

            $cookie_options = array (
                'expires' => time() + 60*60*24*30, 
                'path' => '/', 
                //'domain' => '.foozbat.net', // leading dot for compatibility or use subdomain
                'secure' => false,     // or false
                'httponly' => true,    // or false
                'samesite' => 'Strict' // None || Lax  || Strict
            );

            setcookie($this->auth_token_name, $this->user_session->auth_token, $cookie_options);

            return true;
        }

        return false;
    }

    public function logout(): bool
    {
        $cookie_options = array (
            'expires' => time() - 60*60*24*30, 
            'path' => '/', 
            //'domain' => '.foozbat.net', // leading dot for compatibility or use subdomain
            'secure' => false,     // or false
            'httponly' => true,    // or false
            'samesite' => 'Strict' // None || Lax  || Strict
        );

        setcookie($this->auth_token_name, "", $cookie_options);

        if ($this->user_session !== null) {
            $this->user_session->logged_in = false;
            $this->user_session->save();
        }

        return true;
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
            call_user_func($this->fail_callback);
        }
    }

    public function csrf_required()
    {
        if (!$this->csrf_validated) {
            call_user_func($this->fail_callback);
        }
    }
}