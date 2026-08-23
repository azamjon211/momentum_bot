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

            $text = "🌙 Bugungi yakun: {$done}/{$total} task bajarildi.";
            if ($missed->isNotEmpty()) {
                $text .= "\n\nBajarilmagan tasklar:\n" . $missed->map(fn ($title) => "• {$title}")->implode("\n");
            }

            $this->telegramService->sendMessage($user->telegram_id, $text);
            $plan->update(['summary_sent_at' => now()]);
        }
    }
}
