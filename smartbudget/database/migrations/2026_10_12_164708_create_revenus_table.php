<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Table revenus (CDC §6.4) : structure identique à depenses. */
    public function up(): void
    {
        Schema::create('revenus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('categorie_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenus');
    }
};
