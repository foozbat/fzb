<?php
/**
 * Class JWT
 * 
 * Basic JWT encoding/decoding
 * 
 */

declare(strict_types=1);

namespace Fzb;

use JWT;

class MercureException extends \Exception { }

class Mercure
{
    private string $hub_url;
    private string $jwt;

    private static ?Mercure $instance = null;

    public function __construct(string $hub, ?string $jwt = null)
    {
        // I'm a singleton
        if (self::$instance !== null) {
            throw new AuthException("An Mercure object has already been instantiated.  Cannot create more than one instance.");
        } else {
            self::$instance = $this;
        }

        $this->hub_url = rtrim($hub, '/');

        /*
        $auth = Auth::get_instance();

        if ($jwt === null) {
            if ($auth === null || !$auth->is_logged_in()) {
                throw new MercureException("Cannot create Mercure instance without JWT when no user is logged in.");
            }
        }
        $this->jwt = $jwt ?? $auth->get_user()->get_jwt('mercure');
        */

        if ($jwt === null) {
            throw new MercureException("Cannot create Mercure instance without JWT.");
        }

        $this->jwt = $jwt;
    }

    public function publish(string $topic, string $type = 'message', ?string $data = null): array
    {
        $post_data = http_build_query([
                'topic' => $topic,
                'type' => $type,
                'data' => $data
            ]);

        // change to authorization header once i figure out certificate issues
        //$url = $this->hub_url . '/.well-known/mercure?authorization=' . urlencode($this->jwt);
        //echo $url . "\n";

        $ch = curl_init($this->hub_url . '/.well-known/mercure');
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $this->jwt",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false); // we just want body
        
        // Allow self-signed certificates in development
        if (getenv('MERCURE_ALLOW_SELFSIGNED') === 'true') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'http_code' => $http_code,
                'error' => $error,
                'response' => null
            ];
        }

        curl_close($ch);

        return [
            'success' => $http_code >= 200 && $http_code < 300, // true if 2xx
            'http_code' => $http_code,
            'response' => $response
        ];
    }
}