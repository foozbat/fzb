<?php
/**
 * Class JWT
 * 
 * Basic JWT encoding/decoding
 * 
 */

declare(strict_types=1);

namespace Fzb;

enum JWTAlg: string
{
    case HS256 = 'HS256';
    case HS384 = 'HS384';
    case HS512 = 'HS512';

    public function hash_algo(): string
    {
        return match ($this) {
            self::HS256 => 'sha256',
            self::HS384 => 'sha384',
            self::HS512 => 'sha512',
        };
    }
}

class JWT
{
    private static function base64_url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64_url_decode(string $data): string
    {
        $padding = 4 - (strlen($data) % 4);
        if ($padding < 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encode(array $payload, string $secret, JWTAlg $alg = JWTAlg::HS256): string
    {
        $header_json  = json_encode(['typ' => 'JWT', 'alg' => $alg->value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $header_encoded    = self::base64_url_encode($header_json ?: '');
        $payload_encoded   = self::base64_url_encode($payload_json ?: '');
        $signature_encoded = self::base64_url_encode(hash_hmac($alg->hash_algo(), "$header_encoded.$payload_encoded", $secret, true));

        return "$header_encoded.$payload_encoded.$signature_encoded";
    }

    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header_encoded, $payload_encoded, $signature_encoded] = $parts;

        $signature = self::base64_url_decode($signature_encoded);
        $header_json = self::base64_url_decode($header_encoded);
        $header = json_decode($header_json, true);

        if (!is_array($header) || !isset($header['alg'])) {
            return null;
        }

        // Only support HMAC SHA family for now
        $alg = match ($header['alg']) {
            JWTAlg::HS256->value => JWTAlg::HS256,
            JWTAlg::HS384->value => JWTAlg::HS384,
            JWTAlg::HS512->value => JWTAlg::HS512,
            default => null,
        };

        if ($alg === null) {
            return null;
        }

        $expected_signature = hash_hmac($alg->hash_algo(), "$header_encoded.$payload_encoded", $secret, true);
        if (!hash_equals($expected_signature, $signature)) {
            return null; // Invalid signature
        }

        $payload_json = self::base64_url_decode($payload_encoded);
        return json_decode($payload_json, true);
    }
}