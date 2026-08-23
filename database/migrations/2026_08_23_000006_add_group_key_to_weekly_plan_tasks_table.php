<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plan_tasks', function (Blueprint $table) {
            $table->string('group_key', 20)->nullable()->after('id');
        });

        DB::table('weekly_plan_tasks')->whereNull('group_key')->orderBy('id')->pluck('id')->each(function ($id) {
            DB::table('weekly_plan_tasks')->where('id', $id)->update(['group_key' => Str::random(10)]);
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plan_tasks', function (Blueprint $table) {
            $table->dropColumn('group_key');
        });
    }
};
