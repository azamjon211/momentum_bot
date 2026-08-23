<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TelegramAuthService;
use Closure;
use Illuminate\Http\Request;

class VerifyTelegramWebApp
{
    public function __construct(private TelegramAuthService $telegramAuthService) {}

    public function handle(Request $request, Closure $next)
    {
        $initData = $request->header('X-Telegram-Init-Data');
        $telegramUser = $initData ? $this->telegramAuthService->verifyInitData($initData) : null;

        if (!$telegramUser || !isset($telegramUser['id'])) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::where('telegram_id', $telegramUser['id'])->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $request->attributes->set('telegramUser', $user);

        return $next($request);
    }
}
