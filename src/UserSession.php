<?php
/**
 * Class UserSession
 * 
 * Manages user authentication sessions with auth tokens, CSRF protection, and fingerprinting.
 * Automatically generates unique tokens and validates client fingerprints for security.
 * Extends Base model to include id, created_at, and updated_at fields.
 * 
 * Typical usage is to extend this class to add custom session tracking fields:
 *   class MyUserSession extends Fzb\UserSession {
 *       #[Column(type: Type::VARCHAR, length: 45)]
 *       public string $ip_address;
 *   }
 * 
 * @todo Implement SMS 2FA functionality
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

class UserSessionException extends Exception { }

#[Table('user_sessions')]
class UserSession extends Base
{
    /**
     * @var int ID of the user this session belongs to
     */
    #[Column(type: Type::INT)]
    public int $user_id;

    /**
     * @var bool Whether the session is currently logged in
     */
    #[Column(type: Type::BOOLEAN, default: true)]
    public bool $logged_in;

    /**
     * @var string Unique authentication token stored in cookies
     */
    #[Column(type: Type::VARCHAR, length: 255)]
    public string $auth_token;

    /**
     * @var string CSRF token for form validation
     */
    #[Column(type: Type::VARCHAR, length: 255)]
    public string $csrf_token;

    /**
     * @var string Client fingerprint hash for session validation
     */
    #[Column(type: Type::VARCHAR, length: 255)]
    public string $fingerprint;

    /**
     * @var string|null SMS 2FA verification code
     * @todo Implement SMS 2FA
     */
    #[Column(type: Type::VARCHAR, length: 255, null: true)]
    public ?string $sms_2fa_code;

    /**
     * @var string|null Expiration timestamp for SMS 2FA code
     * @todo Implement SMS 2FA
     */
    #[Column(type: Type::VARCHAR, length: 255, null: true)]
    public ?string $sms_2fa_expires_at;

    /**
     * Constructor - automatically generates auth token, CSRF token, and fingerprint
     *
     * @param mixed ...$params session data including user_id
     */
    public function __construct(...$params)
    {
        $this->auth_token = self::generate_uuid();
        $this->csrf_token = self::generate_uuid();
        $this->fingerprint = self::generate_fingerprint();

        parent::__construct(...$params);
    }

    /**
     * Generates a RFC 4122 compliant UUID v4
     *
     * @return string UUID in format xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     */
    public static function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }

    /**
     * Generates a client fingerprint hash based on browser characteristics
     *
     * @return string MD5 hash of user agent, encoding, language, DNT, and cookie support
     */
    public static function generate_fingerprint() {
        $fingerprint = '';
    
        $fingerprint .= "useragent:";
        $fingerprint .= $_SERVER['HTTP_USER_AGENT'] ?? '';
        $fingerprint .= "encoding:";
        $fingerprint .= $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        $fingerprint .= "lang:";
        $fingerprint .= $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $fingerprint .= "dnt:";
        $fingerprint .= $_SERVER['HTTP_DNT'] ?? '';
        $fingerprint .= "cookie:";
        $fingerprint .= isset($_COOKIE) ? '1' : '';
    
        return md5($fingerprint);
    }

    /**
     * Validates that current client fingerprint matches stored fingerprint
     *
     * @return bool true if fingerprints match, false otherwise
     */
    public function validate_fingerprint() {
        return $this->fingerprint == self::generate_fingerprint();
    }
}