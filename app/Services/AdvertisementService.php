<?php

namespace App\Services;

use App\Models\PromoChannel;

class AdvertisementService
{
    private const MESSAGES = [
        "🎯 <b>Intizom yo'lida yolg'iz emassiz!</b>\n\nHar kuni rejalaringizni eslatib turadigan, bajarganingizda tabriklaydigan, do'stlaringiz bilan solishtirib turadigan bot bilan tanishing.\n\n✅ Kunlik reja va eslatmalar\n📊 Streak va statistika\n🏅 Yutuqlar tizimi\n🏆 Do'stlar reytingi",
        "🔥 <b>Odatlaringizni nazorat qilishga tayyormisiz?</b>\n\nBu bot orqali haftalik reja tuzasiz, har kuni eslatma olasiz, streak yig'asiz va do'stlaringiz bilan kim ko'proq intizomli ekanini solishtirasiz.",
        "📈 <b>Kichik qadamlar katta natijalarga olib boradi.</b>\n\nBu bot sizga har kuni nima qilish kerakligini eslatadi, bajarganingizni tabriklaydi, bajarmaganingizda esa turtki beradi. Do'stlaringiz bilan birga sinab ko'ring!",
    ];

    public function __construct(private TelegramService $telegramService) {}

    public function sendWeeklyAds(): void
    {
        $botUsername = config('services.telegram.bot_username');
        $link = "https://t.me/{$botUsername}";

        $channels = PromoChannel::where(function ($q) {
            $q->whereNull('last_ad_sent_at')
                ->orWhere('last_ad_sent_at', '<=', now()->subDays(7));
        })->get();

        foreach ($channels as $channel) {
            $text = self::MESSAGES[array_rand(self::MESSAGES)]."\n\n👉 {$link}";
            $this->telegramService->sendMessage($channel->chat_id, $text, null, 'HTML');
            $channel->update(['last_ad_sent_at' => now()]);
        }
    }
}
