<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weekly_plan_task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type');
            $table->unsignedInteger('target_value')->nullable();
            $table->string('target_unit')->nullable();
            $table->time('remind_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_done')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_plan_tasks');
    }
};
