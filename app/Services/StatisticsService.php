<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\User;

class StatisticsService
{
    public function buildFor(User $user): array
    {
        $dailyPlans = DailyPlan::where('user_id', $user->id)
            ->whereHas('tasks')
            ->with('tasks')
            ->orderByDesc('date')
            ->get();

        $totalDays = $dailyPlans->count();
        $totalTasks = $dailyPlans->sum(fn ($dp) => $dp->tasks->count());
        $totalDone = $dailyPlans->sum(fn ($dp) => $dp->tasks->where('is_done', true)->count());
        $completionRate = $totalTasks > 0 ? (int) round($totalDone / $totalTasks * 100) : 0;

        $today = now($user->timezone)->toDateString();
        $streak = 0;

        foreach ($dailyPlans as $dailyPlan) {
            $tasks = $dailyPlan->tasks;
            $allDone = $tasks->isNotEmpty() && $tasks->where('is_done', false)->isEmpty();

            if ($dailyPlan->date->toDateString() === $today && !$allDone) {
                continue;
            }

            if (!$allDone) {
                break;
            }

            $streak++;
        }

        $recent = $dailyPlans->take(7)->map(fn ($dp) => [
            'date' => $dp->date->toDateString(),
            'done' => $dp->tasks->where('is_done', true)->count(),
            'total' => $dp->tasks->count(),
        ])->values();

        return [
            'total_days' => $totalDays,
            'completion_rate' => $completionRate,
            'streak' => $streak,
            'recent' => $recent,
        ];
    }
}
