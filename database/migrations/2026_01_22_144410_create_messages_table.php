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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');

            // 'user' = lo que escribe el cliente
            // 'assistant' = lo que responde tu IA
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content'); // El texto del mensaje
            $table->text('media_url')->nullable();
            $table->string('media_type')->nullable();
            $table->string('media_path')->nullable();
            $table->string('whatsapp_id')->nullable(); // ID único del mensaje en WA
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
