<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;

class BadgeService
{
    private const STREAK_MILESTONES = [
        3 => ['key' => 'streak_3', 'label' => '🥉 3 kunlik streak'],
        7 => ['key' => 'streak_7', 'label' => '🥈 7 kunlik streak'],
        14 => ['key' => 'streak_14', 'label' => '🎖 14 kunlik streak'],
        30 => ['key' => 'streak_30', 'label' => '🥇 30 kunlik streak'],
        60 => ['key' => 'streak_60', 'label' => '💎 60 kunlik streak'],
        100 => ['key' => 'streak_100', 'label' => '👑 100 kunlik streak'],
        365 => ['key' => 'streak_365', 'label' => '🏆 Yillik chempion'],
    ];

    private const TASK_MILESTONES = [
        10 => ['key' => 'tasks_10', 'label' => '🌱 10 ta vazifa'],
        50 => ['key' => 'tasks_50', 'label' => '🔥 50 ta vazifa'],
        100 => ['key' => 'tasks_100', 'label' => '⚡ 100 ta vazifa'],
        250 => ['key' => 'tasks_250', 'label' => '🚀 250 ta vazifa'],
        500 => ['key' => 'tasks_500', 'label' => '🌟 500 ta vazifa'],
        1000 => ['key' => 'tasks_1000', 'label' => '👑 1000 ta vazifa'],
    ];

    public function __construct(private StatisticsService $statisticsService) {}

    public function checkAndAward(User $user): array
    {
        $stats = $this->statisticsService->buildFor($user);
        $newlyAwarded = [];

        foreach (self::STREAK_MILESTONES as $threshold => $badge) {
            if ($stats['streak'] >= $threshold && $this->award($user, $badge)) {
                $newlyAwarded[] = $badge['label'];
            }
        }

        foreach (self::TASK_MILESTONES as $threshold => $badge) {
            if ($stats['total_done'] >= $threshold && $this->award($user, $badge)) {
                $newlyAwarded[] = $badge['label'];
            }
        }

        return $newlyAwarded;
    }

    private function award(User $user, array $badge): bool
    {
        $record = Badge::firstOrCreate(
            ['user_id' => $user->id, 'type' => $badge['key']],
            ['earned_at' => now()]
        );

        return $record->wasRecentlyCreated;
    }

    public function allBadgesFor(User $user): array
    {
        $earnedTypes = Badge::where('user_id', $user->id)->pluck('type')->all();
        $all = [];

        foreach ([...self::STREAK_MILESTONES, ...self::TASK_MILESTONES] as $badge) {
            $all[] = [
                'label' => $badge['label'],
                'earned' => in_array($badge['key'], $earnedTypes),
            ];
        }

        return $all;
    }

    public function earnedCountFor(User $user): int
    {
        return Badge::where('user_id', $user->id)->count();
    }
}
