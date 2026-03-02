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
        Schema::create('recorded_phones', function (Blueprint $table) {
            $table->id(); // bigint unsigned auto increment
            $table->string('phone'); // varchar(255) NOT NULL
            $table->string('normalized_phone')->nullable()->index();
            $table->timestamp('imported_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recorded_phones');
    }
};
