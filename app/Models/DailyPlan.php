<?php

namespace App\Models;
use App\Models\User;
use App\Models\DailyPlanTask;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;use Illuminate\Database\Eloquent\Relations\HasMany;


class DailyPlan extends Model
{
    protected $fillable = [
        'user_id', 'weekly_plan_id', 'date'
    ];
    protected $casts = [
        'date'=> 'date'
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function weeklyPlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class);
    }
    public function tasks(): HasMany {
        return $this->hasMany(DailyPlanTask::class);
    }
}
