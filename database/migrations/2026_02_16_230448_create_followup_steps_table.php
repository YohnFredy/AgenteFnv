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
        Schema::create('followup_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('followup_campaigns')
                ->cascadeOnDelete();

            $table->string('message_type');
            // text, template, video, image, etc.

            $table->text('message_content');
            $table->integer('delay')->default(1);
            $table->timestamps();

            $table->index(['campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('followup_steps');
    }
};
