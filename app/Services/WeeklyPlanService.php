<?php

namespace App\Services;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanTask;
use Illuminate\Support\Facades\DB;
class WeeklyPlanService {
    public function create(User $user, string $name){
        return $user->weeklyPlans()->create([
            'name' => $name,
            'is_active' => false,
        ]);
    }
    public function activate(WeeklyPlan $plan){
        DB::transaction(function() use ($plan){
            WeeklyPlan::where('user_id', $plan->user_id)
                ->where('id', '!=', $plan->id)
                ->update(['is_active' => false]);

            $plan->update(['is_active' => true]);
        });
        return $plan->fresh();
    }
    public function addTask(WeeklyPlan $plan, array $data){
        return $plan->tasks()->create($data);
    }

    public function deactivateExpired(){
        $expired = WeeklyPlan::where('is_active', true)
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<=', now()->toDateString())
            ->with('user')
            ->get();

        foreach($expired as $plan){
            $plan->update(['is_active' => false]);
        }

        return $expired;
    }
}
