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
        Schema::table('messages', function (Blueprint $table) {
            $table->string('type')->nullable()->after('content'); // e.g., 'text', 'template', 'image'
            $table->string('status')->nullable()->after('type'); // e.g., 'sent', 'delivered', 'read', 'failed'
            $table->json('metadata')->nullable()->after('status'); // Additional info
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'status', 'metadata']);
        });
    }
};
