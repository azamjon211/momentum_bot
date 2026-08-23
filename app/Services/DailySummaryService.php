<?php

namespace App\Services;

use App\Models\DailyPlan;

class DailySummaryService
{
    public function __construct(private TelegramService $telegramService) {}

    public function sendDueSummaries(): void
    {
        $plans = DailyPlan::whereNull('summary_sent_at')
            ->whereDate('date', now()->toDateString())
            ->with(['user', 'tasks'])
            ->get();

        foreach ($plans as $plan) {
            $user = $plan->user;
            $localNow = now($user->timezone);

            if ($localNow->format('H:i') < '21:00') {
                continue;
            }

            $total = $plan->tasks->count();
            if ($total === 0) {
                continue;
            }

            $done = $plan->tasks->where('is_done', true)->count();
            $missed = $plan->tasks->where('is_done', false)->pluck('title');

            if ($missed->isEmpty()) {
                $text = "🌙 <b>Ajoyib kun!</b>\n\nBugun barcha {$total} ta vazifangizga amal qildingiz. Intizom yo'lida davom etyapsiz 💪";
            } else {
                $missedList = $missed->map(fn ($title) => "• {$title}")->implode("\n");
                $text = "🌙 <b>Bugungi yakun</b>\n\n"
                    ."Bugun rejangizga to'liq amal qilmadingiz — {$done}/{$total} vazifa bajarildi.\n\n"
                    ."Bajarilmadi:\n{$missedList}\n\n"
                    ."Ertaga yaxshiroq bo'ladi, davom eting! 🔥";
            }

            $this->telegramService->sendMessage($user->telegram_id, $text, null, 'HTML');
            $plan->update(['summary_sent_at' => now()]);
        }
    }
}
