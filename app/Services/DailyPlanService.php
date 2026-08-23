<?php
namespace App\Services;
use App\Models\DailyPlanTask;
use App\Models\User;
use Carbon\Carbon;
use App\Models\DailyPlan;
use Illuminate\Support\Facades\DB;

class DailyPlanService
{
    public function generateForDate(User $user, Carbon $date){
        $activeplan = $user->weeklyPlans()->where('is_active', 1)->first();
        if(!$activeplan) return null;
        $existing = DailyPlan::where('user_id', $user->id)
            ->where('date', $date->toDateString())
            ->first();
        if($existing){return $existing->load('tasks');}
        $weekday = strtolower($date->format("l"));
        return DB::transaction(function () use($user, $activeplan, $date, $weekday){
            $dailyplan = DailyPlan::create([
                'user_id' =>$user->id,
                'weekly_plan_id' => $activeplan->id,
                'date' => $date->toDateString(),
            ]);
            $tasks = $activeplan->tasks()->where('weekday', $weekday) ->orderby('position')->get();
            foreach($tasks as $task){
                $dailyplan->tasks()->create([
                    'weekly_plan_task_id' => $task->id,
                    'title' => $task->title,
                    'type' => $task->type,
                    'target_value' => $task->target_value,
                    'target_unit' => $task->target_unit,
                    'remind_at' => $task->remind_at,
                    'position' => $task->position,
                ]);
            }
            return $dailyplan->fresh('tasks');
        });
    }
    public function generateForAllActiveUsers(Carbon $date){
        $users = User::WhereHas('weeklyPlans', fn($q)=> $q->where('is_active', 1))->get();
        foreach($users as $user){
            $this->generateForDate($user, $date);
        }
    }
    public function complete(DailyPlanTask $task){
        $task->update([
            'is_done' =>true,
            'completed_at' => now(),
        ]);
        return $task->fresh();
}
}
