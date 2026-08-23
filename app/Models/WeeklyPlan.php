<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyPlan extends Model
{
    protected $fillable = [
        'user_id', 'name', 'is_active', 'duration_days', 'ends_at'
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'ends_at' => 'date',
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function tasks(){
        return $this->hasMany(WeeklyPlanTask::class);
    }
}
