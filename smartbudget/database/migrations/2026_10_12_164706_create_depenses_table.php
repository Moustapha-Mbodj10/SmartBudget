<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Table depenses (CDC §6.3). */
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // RESTRICT : une catégorie utilisée par une dépense ne peut pas être supprimée
            $table->foreignId('categorie_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->string('description', 255)->nullable();
            $table->timestamps();

            // Statistiques par période
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};
