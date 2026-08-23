<?php

namespace App\Services;

use App\Models\Challenge;

class ChallengeReminderService
{
    public function __construct(
        private TelegramService $telegramService,
        private ChallengeService $challengeService,
    ) {}

    public function sendDueReminders(): void
    {
        $challenges = Challenge::where('is_active', true)
            ->whereNotNull('remind_at')
            ->with('participants.user')
            ->get();

        foreach ($challenges as $challenge) {
            foreach ($challenge->participants as $participant) {
                $user = $participant->user;
                $localNow = now($user->timezone);

                if ($localNow->format('H:i:s') < $challenge->remind_at) {
                    continue;
                }

                if ($this->challengeService->hasLoggedToday($challenge, $user)) {
                    continue;
                }

                if ($participant->last_reminded_at && $participant->last_reminded_at->diffInMinutes(now()) < 60) {
                    continue;
                }

                $this->telegramService->sendChallengeReminder($challenge, $user);
                $participant->update(['last_reminded_at' => now()]);
            }
        }
    }
}
