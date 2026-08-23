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
        Schema::create('weekly_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weekly_plan_id');
            $table->string('weekday');
            $table->string('title');
            $table->string('type');
            $table->unsignedInteger('target_value')->nullable();
            $table->string('target_unit')->nullable();
            $table->time('remind_at')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->foreign('weekly_plan_id')->references('id')->on('weekly_plans');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_tasks');
    }
};
