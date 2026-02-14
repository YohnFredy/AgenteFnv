<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Activar todos los chats que estén como inactivos sin razón
        // (Esto corrige el bug donde chats nuevos se creaban como inactivos)

        DB::statement('UPDATE chats SET is_active = 1 WHERE is_active = 0 OR is_active IS NULL');

        // Log para saber cuántos se actualizaron
        $updated = DB::table('chats')
            ->where(function ($query) {
                $query->where('is_active', 0)
                    ->orWhereNull('is_active');
            })
            ->count();

        if ($updated > 0) {
            \Log::info("Migration: Activados {$updated} chats que estaban inactivos");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversible - no podemos saber cuáles debían estar inactivos
    }
};
