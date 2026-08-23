<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use App\Services\WeeklyPlanService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-plan-expirations')]
#[Description('Deactivate weekly plans whose duration has ended and notify the user')]
class CheckPlanExpirations extends Command
{
    public function __construct(
        private WeeklyPlanService $weeklyPlanService,
        private TelegramService $telegramService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $expired = $this->weeklyPlanService->deactivateExpired();

        foreach ($expired as $plan) {
            $this->telegramService->sendMessage(
                $plan->user->telegram_id,
                "🎉 \"{$plan->name}\" rejangiz muddati tugadi! Yangi reja yaratish uchun \"🗓 Reja yaratish\" tugmasini bosing."
            );
        }
    }
}
