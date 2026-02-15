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
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->string('remote_jid')->unique(); // El ID de WhatsApp (57300...@s.whatsapp.net)
            $table->string('provider')->default('evolution');
            $table->string('name')->nullable();     // El nombre (PushName)
            $table->tinyInteger('stage')->unsigned()->default(0)->index();
            $table->string('status')->default('open'); // Por si quieres cerrar tickets luego
            $table->text('system_instruction')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
