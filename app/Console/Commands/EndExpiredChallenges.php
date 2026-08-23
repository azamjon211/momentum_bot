<?php

namespace App\Console\Commands;

use App\Services\ChallengeService;
use App\Services\TelegramService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:end-expired-challenges')]
#[Description('Deactivate challenges whose duration has ended and announce the winner')]
class EndExpiredChallenges extends Command
{
    public function __construct(
        private ChallengeService $challengeService,
        private TelegramService $telegramService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $expired = $this->challengeService->deactivateExpired();

        foreach ($expired as $challenge) {
            $leaderboard = $this->challengeService->leaderboardFor($challenge);
            $winner = $leaderboard[0]['name'] ?? null;

            $challenge->load('participants.user');

            foreach ($challenge->participants as $participant) {
                $text = "🏁 <b>\"{$challenge->title}\"</b> challenge yakunlandi!";
                if ($winner) {
                    $text .= "\n\n🏆 G'olib: {$winner}";
                }

                $this->telegramService->sendMessage($participant->user->telegram_id, $text, null, 'HTML');
            }
        }
    }
}
