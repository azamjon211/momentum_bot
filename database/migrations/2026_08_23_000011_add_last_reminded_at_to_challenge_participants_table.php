<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenge_participants', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('challenge_participants', function (Blueprint $table) {
            $table->dropColumn('last_reminded_at');
        });
    }
};
