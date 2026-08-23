<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeDailyLog extends Model
{
    protected $fillable = ['challenge_id', 'user_id', 'date', 'is_done', 'value', 'logged_at'];
    protected $casts = [
        'date' => 'date',
        'is_done' => 'boolean',
        'value' => 'integer',
        'logged_at' => 'datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
