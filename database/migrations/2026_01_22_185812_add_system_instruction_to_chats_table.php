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
        Schema::table('chats', function (Blueprint $table) {
            // $table->unique('remote_jid'); // Ya existe
            $table->text('system_instruction')->nullable()->after('status');
            $table->boolean('is_active')->default(true)->after('system_instruction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropUnique(['remote_jid']);
            $table->dropColumn(['system_instruction', 'is_active']);
        });
    }
};
