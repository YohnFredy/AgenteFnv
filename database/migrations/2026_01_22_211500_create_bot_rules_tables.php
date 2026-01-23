<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_rules', function (Blueprint $table) {
            $table->id();
            $table->integer('trigger_stage')->default(0);
            $table->integer('next_stage')->default(0);
            $table->text('keywords'); // Comma separated
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bot_rule_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_rule_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('delay')->default(1); // Seconds
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_rule_messages');
        Schema::dropIfExists('bot_rules');
    }
};
