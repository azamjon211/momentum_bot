<?php

namespace App\Models;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyPlanTask extends Model
{
    protected $fillable = [
        'weekly_plan_id', 'weekday', 'title', 'type', 'target_value', 'target_unit', 'remind_at', 'position',];
    protected $casts = [
        'type' => \App\Enums\TaskType::class, 'target_value' =>'integer',
    ];
    public function weeklyPlan(): BelongsTo{
        return $this->belongsTo(WeeklyPlan::class);
    }
}
