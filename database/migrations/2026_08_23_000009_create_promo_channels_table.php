<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_channels', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique();
            $table->string('title')->nullable();
            $table->timestamp('last_ad_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_channels');
    }
};
