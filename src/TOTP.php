<?php
/**
 * Class TOTP
 * 
 * Basic TOTP code generator/validator
 * 
 * usage: Instantiate with $totp = new Fzb\TOTP();
 * 
 * @author  Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb;

use Exception;

class TOTPException extends Exception { }

class TOTP
{
    private string $secret;
    private int $digits;
    private int $time_step;
    private string $algo;
    private string $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(string $secret, int $digits = 6, int $time_step = 30, string $algo = 'sha1')
    {
        $this->secret = $secret;
        $this->digits = $digits;
        $this->time_step = $time_step;
        $this->algo = $algo;
    }

    public function get_code(?int $timestamp = null): string
    {
        $timestamp ??= time();
        
        return $this->generate_code($this->secret, $timestamp);
    }

    public function validate_code(string $input_code, int $allowed_drift = 1, ?int $timestamp = null): bool
    {
        $timestamp ??= time();

        for ($i = -$allowed_drift; $i <= $allowed_drift; $i++) {
            $test_time = $timestamp + ($i * $this->time_step);
            $valid_code = $this->generate_code($this->secret, $test_time);

            if (hash_equals($valid_code, $input_code)) {
                return true;
            }
        }

        return false;
    }

    private function generate_code(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, $this->time_step);
        $binary_counter = pack('N*', 0) . pack('N*', $counter);
        $key = $this->base32_decode($secret);

        $hash = hash_hmac($this->algo, $binary_counter, $key, true);
        $offset = ord($hash[-1]) & 0x0F;

        $segment = substr($hash, $offset, 4);
        $number = unpack('N', $segment)[1] & 0x7FFFFFFF;

        return str_pad((string)($number % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    private function base32_decode(string $b32): string
    {
        $binary = '';

        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));

        foreach (str_split($b32) as $char) {
            $binary .= str_pad(decbin(strpos($this->alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }

        $data = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $data .= chr(bindec($byte));
            }
        }

        return $data;
    }

    public static function generate_secret(int $length = 16): string
    {
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $this->alphabet[random_int(0, strlen($chars) - 1)];
        }

        return $secret;
    }
}


