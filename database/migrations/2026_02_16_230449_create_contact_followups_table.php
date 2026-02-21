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
        Schema::create('contact_followups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chat_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('campaign_id')
                ->constrained('followup_campaigns')
                ->cascadeOnDelete();

            $table->timestamp('scheduled_at')->nullable();

            $table->timestamp('last_interaction_at')->nullable();

            $table->string('status')->default('active');
            // active, paused, completed, cancelled
            $table->timestamps();

            $table->index(['chat_id', 'campaign_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_followups');
    }
};
