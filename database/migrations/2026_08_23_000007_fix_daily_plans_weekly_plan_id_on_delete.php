<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropForeign(['weekly_plan_id']);
            $table->foreign('weekly_plan_id')->references('id')->on('weekly_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropForeign(['weekly_plan_id']);
            $table->foreign('weekly_plan_id')->references('id')->on('weekly_plans');
        });
    }
};
