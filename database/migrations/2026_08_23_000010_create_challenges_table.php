<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->unsignedInteger('target_value')->nullable();
            $table->string('target_unit')->nullable();
            $table->time('remind_at')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->string('invite_code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique(['challenge_id', 'user_id']);
        });

        Schema::create('challenge_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('value')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();
            $table->unique(['challenge_id', 'user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_daily_logs');
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenges');
    }
};
