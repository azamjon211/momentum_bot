<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoChannel extends Model
{
    protected $fillable = ['chat_id', 'title', 'last_ad_sent_at'];
    protected $casts = ['last_ad_sent_at' => 'datetime'];
}
