<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi de connexion, pour le tableau de bord de l'administrateur :
 *  - derniere_activite : dernière requête faite par la personne connectée (mise à jour au plus une fois par minute) ;
 *  - deconnecte_le     : moment de la dernière déconnexion volontaire (vide tant que la personne est connectée).
 * Rien de plus n'est conservé : ni adresse IP, ni pages visitées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('derniere_activite')->nullable()->after('statut');
            $table->timestamp('deconnecte_le')->nullable()->after('derniere_activite');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['derniere_activite', 'deconnecte_le']);
        });
    }
};
