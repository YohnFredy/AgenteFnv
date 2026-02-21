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
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->integer('followup_delay_hours')->default(0)->after('match_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropColumn('followup_delay_hours');
        });
    }
};
