<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $fillable = [
        'daily_plan_task_id', 'sent_at'];
   protected $casts = [
       'sent_at' => 'datetime',
        ];
   public function dailyPlanTask(): \Illuminate\Database\Eloquent\Relations\BelongsTo
   {
       return $this->belongsTo(DailyPlanTask::class);
   }
}
