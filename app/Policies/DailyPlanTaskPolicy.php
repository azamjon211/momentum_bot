<?php
namespace App\Policies;

use App\Models\DailyPlanTask;
use App\Models\User;

class DailyPlanTaskPolicy
{
    public function update(User $user, DailyPlanTask $task):bool{
        return $user->id === $task->dailyplan->user_id;
    }
    public function view(User $user, DailyPlanTask $task):bool{
        return $user->id === $task->dailyplan->user_id;
    }

}
