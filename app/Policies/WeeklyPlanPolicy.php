<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeeklyPlan;
class WeeklyPlanPolicy {
    public function view (User $user, WeeklyPlan $weeklyPlan):bool
    {
        return $user->id === $weeklyPlan->user_id;
    }
    public function update (User $user, WeeklyPlan $weeklyPlan):bool
    {
        return $user->id ===$weeklyPlan->user_id;
    }
    public function delete (User $user, WeeklyPlan $weeklyPlan):bool
    {
        return $user->id === $weeklyPlan->user_id;
    }
}

