<?php

namespace App\Services;

class TelegramAuthService
{
    public function verifyInitData(string $initData): ?array
    {
        parse_str($initData, $data);

        if (!isset($data['hash']) || !isset($data['user'])) {
            return null;
        }

        $hash = $data['hash'];
        unset($data['hash']);
        ksort($data);

        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = "{$key}={$value}";
        }
        $dataCheckString = implode("\n", $pairs);

        $secretKey = hash_hmac('sha256', config('services.telegram.bot_token'), 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($computedHash, $hash)) {
            return null;
        }

        return json_decode($data['user'], true);
    }
}
