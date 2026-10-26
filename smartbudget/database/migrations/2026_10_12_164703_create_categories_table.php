<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Table categories (CDC §6.2). */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('description', 255)->nullable();
            $table->enum('type', ['depense', 'revenu']);
            $table->timestamps();

            // Nom unique par utilisateur et par type
            $table->unique(['user_id', 'nom', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
