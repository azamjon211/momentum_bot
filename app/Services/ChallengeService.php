<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeDailyLog;
use App\Models\ChallengeParticipant;
use App\Models\User;
use Illuminate\Support\Str;

class ChallengeService
{
    public function generateInviteCode(): string
    {
        do {
            $code = 'CH'.strtoupper(Str::random(6));
        } while (Challenge::where('invite_code', $code)->exists());

        return $code;
    }

    public function create(User $creator, array $data): Challenge
    {
        $durationDays = $data['duration_days'] ?? null;

        $challenge = Challenge::create([
            'creator_id' => $creator->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'target_value' => $data['target_value'] ?? null,
            'target_unit' => $data['target_unit'] ?? null,
            'remind_at' => $data['remind_at'] ?? null,
            'duration_days' => $durationDays,
            'starts_at' => now()->toDateString(),
            'ends_at' => $durationDays ? now()->addDays($durationDays)->toDateString() : null,
            'invite_code' => $this->generateInviteCode(),
        ]);

        $this->join($challenge, $creator);

        return $challenge;
    }

    public function join(Challenge $challenge, User $user): ChallengeParticipant
    {
        return ChallengeParticipant::firstOrCreate(
            ['challenge_id' => $challenge->id, 'user_id' => $user->id],
            ['joined_at' => now()]
        );
    }

    public function isParticipant(Challenge $challenge, User $user): bool
    {
        return ChallengeParticipant::where('challenge_id', $challenge->id)->where('user_id', $user->id)->exists();
    }

    public function logProgress(Challenge $challenge, User $user, ?int $value = null): ChallengeDailyLog
    {
        $isDone = $challenge->type->value === 'checkbox'
            ? true
            : ($value !== null && (!$challenge->target_value || $value >= $challenge->target_value));

        return ChallengeDailyLog::updateOrCreate(
            ['challenge_id' => $challenge->id, 'user_id' => $user->id, 'date' => now($user->timezone)->toDateString()],
            ['value' => $value, 'is_done' => $isDone, 'logged_at' => now()]
        );
    }

    public function hasLoggedToday(Challenge $challenge, User $user): bool
    {
        return ChallengeDailyLog::where('challenge_id', $challenge->id)
            ->where('user_id', $user->id)
            ->where('date', now($user->timezone)->toDateString())
            ->exists();
    }

    public function leaderboardFor(Challenge $challenge): array
    {
        $participants = $challenge->participants()->with('user')->get();
        $isCheckbox = $challenge->type->value === 'checkbox';

        $rows = $participants->map(function ($participant) use ($challenge) {
            $logs = ChallengeDailyLog::where('challenge_id', $challenge->id)
                ->where('user_id', $participant->user_id)
                ->get();

            $logsByDate = $logs->keyBy(fn ($log) => $log->date->toDateString());
            $startDate = $challenge->starts_at->toDateString();

            $recent = collect(range(6, 0))->map(function ($daysAgo) use ($logsByDate, $startDate) {
                $date = now()->subDays($daysAgo)->toDateString();
                if ($date < $startDate) {
                    return ['status' => 'na'];
                }
                $log = $logsByDate->get($date);
                return ['status' => ($log && $log->is_done) ? 'done' : 'missed'];
            })->values();

            return [
                'name' => $participant->user->name,
                'user_id' => $participant->user_id,
                'total_value' => $logs->sum('value'),
                'days_done' => $logs->where('is_done', true)->count(),
                'recent' => $recent,
            ];
        });

        return $rows->sort(function ($a, $b) use ($isCheckbox) {
            if ($isCheckbox) {
                return $b['days_done'] <=> $a['days_done'];
            }
            return ($b['total_value'] <=> $a['total_value']) ?: ($b['days_done'] <=> $a['days_done']);
        })->values()->all();
    }

    public function deactivateExpired()
    {
        $expired = Challenge::where('is_active', true)
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<=', now()->toDateString())
            ->get();

        foreach ($expired as $challenge) {
            $challenge->update(['is_active' => false]);
        }

        return $expired;
    }
}
