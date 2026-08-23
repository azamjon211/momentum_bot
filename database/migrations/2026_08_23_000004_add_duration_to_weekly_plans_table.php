<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->nullable()->after('is_active');
            $table->date('ends_at')->nullable()->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropColumn(['duration_days', 'ends_at']);
        });
    }
};
