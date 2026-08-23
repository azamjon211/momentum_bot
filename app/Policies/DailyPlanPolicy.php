<?php

use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\User;
use App\Policies;

class DailyPlanPolicy {
    public function view (User $user, DailyPlan $plan):bool{
        return $user->id === $plan->user_id;
    }
}
