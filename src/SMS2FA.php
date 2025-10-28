<?php

declare(strict_types=1);

namespace Fzb;

use Exception;

class SMS2FAException extends Exception { }

class SMS2FA
{
    private int $code_length;
    private int $expires_in_seconds;

    public function __construct(int $code_length = 6, int $expires_in_seconds = 300)
    {
        $this->code_length = $code_length;
        $this->expires_in_seconds = $expires_in_seconds;
    }

    public function generate_code(UserSession $session): string
    {
        $code = str_pad((string)random_int(0, (10 ** $this->code_length) - 1), $this->code_length, '0', STR_PAD_LEFT);

        $session->sms_2fa_code = $code;
        $session->sms_2fa_expires_at = time() + $this->expires_in_seconds;
        $session->save();

        return $code;
    }

    public function validate_code(UserSession $session, string $input_code): bool
    {
        if (!isset($session->sms_2fa_code) || !isset($session->sms_2fa_expires_at)) {
            return false;
        }

        if ((int)$session->sms_2fa_expires_at < time()) {
            $this->clear_code($session);
            return false;
        }

        if (hash_equals($session->sms_2fa_code, $input_code)) {
            $this->clear_code($session);
            return true;
        }

        return false;
    }

    public function send_code(UserSession $session, string $phone_number, callable $sms_sender): bool
    {
        $code = $this->generate_code($session);
        return $sms_sender($phone_number, $code);
    }

    private function clear_code(UserSession $session): void
    {
        $session->sms_2fa_code = null;
        $session->sms_2fa_expires_at = null;
        $session->save();
    }
}
