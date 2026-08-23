<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    protected $fillable = [
        'creator_id', 'title', 'type', 'target_value', 'target_unit',
        'remind_at', 'duration_days', 'starts_at', 'ends_at', 'invite_code', 'is_active',
    ];

    protected $casts = [
        'type' => TaskType::class,
        'target_value' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(ChallengeDailyLog::class);
    }
}
