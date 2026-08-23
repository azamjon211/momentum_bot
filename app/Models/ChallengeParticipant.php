<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeParticipant extends Model
{
    protected $fillable = ['challenge_id', 'user_id', 'joined_at', 'last_reminded_at'];
    protected $casts = ['joined_at' => 'datetime', 'last_reminded_at' => 'datetime'];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
