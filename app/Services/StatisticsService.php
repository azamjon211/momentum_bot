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

        $recent = $dailyPlans->take(28)->map(fn ($dp) => [
            'date' => $dp->date->toDateString(),
            'done' => $dp->tasks->where('is_done', true)->count(),
            'total' => $dp->tasks->count(),
        ])->values();

        $durationTotals = [];
        $countTotals = [];

        foreach ($dailyPlans as $dailyPlan) {
            foreach ($dailyPlan->tasks as $task) {
                if (!$task->is_done || !$task->target_value) {
                    continue;
                }

                $unit = $task->target_unit ?: '';
                if ($task->type->value === 'duration') {
                    $durationTotals[$unit] = ($durationTotals[$unit] ?? 0) + $task->target_value;
                } elseif ($task->type->value === 'count') {
                    $countTotals[$unit] = ($countTotals[$unit] ?? 0) + $task->target_value;
                }
            }
        }

        return [
            'total_days' => $totalDays,
            'total_done' => $totalDone,
            'completion_rate' => $completionRate,
            'streak' => $streak,
            'recent' => $recent,
            'duration_totals' => $durationTotals,
            'count_totals' => $countTotals,
        ];
    }
}
