<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanTask extends Model
{
    protected $fillable = [
        'daily_plan_id', 'weekly_plan_task_id', 'title', 'type', 'target_value', 'target_unit', 'remind_at', 'position', 'is_done', 'completed_at'
    ];
    protected $casts = [
        'type' => TaskType::class,
        'target_value' => 'integer',
        'is_done' => 'boolean',
        'completed_at' => 'datetime'
    ];
    public function dailyPlan(): BelongsTo {
        return $this->belongsTo(DailyPlan::class);
    }
    public function weeklyPlanTask(): BelongsTo {
        return $this->belongsTo(WeeklyPlanTask::class);
    }
    public function reminders(){
        return $this->hasMany(Reminder::class);
    }


}
