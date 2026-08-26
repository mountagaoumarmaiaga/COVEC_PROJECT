<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Écran 2 « Sorties » (§2) : un véhicule se sert à la cuve.
 * Date, véhicule, chauffeur, litres servis, index compteur.
 *
 * Les colonnes distance_parcourue, consommation, moyenne_reference,
 * ecart_pourcentage et anomalie sont calculées par ConsommationService à
 * chaque écriture. Elles sont stockées pour que l'historique et l'export
 * restent lisibles sans recalculer toute la chaîne à la lecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorties', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_sortie')->comment('Horodatage du plein');
            $table->foreignId('vehicule_id')->constrained('vehicules')->cascadeOnDelete();
            $table->foreignId('chauffeur_id')->constrained('chauffeurs')->restrictOnDelete();
            $table->decimal('litres_servis', 10, 2);
            // Prix du litre au moment du plein, repris du carburant du
            // véhicule. Enregistré et non recalculé : un prix qui change
            // le mois suivant ne doit pas réécrire l'historique.
            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('index_compteur', 14, 2)->comment('Kilométrage ou heures moteur selon le véhicule');

            // Point à trancher du cahier des charges : rapprochement avec le
            // totalisateur de la pompe. Nullable tant que l'équipement n'est
            // pas confirmé, pour ne pas bloquer la saisie.
            $table->decimal('index_pompe', 14, 2)->nullable()->comment('Index du totalisateur de pompe, si équipée');

            $table->decimal('distance_parcourue', 14, 2)->nullable()->comment('Écart avec l index précédent (km ou h)');
            $table->decimal('consommation', 12, 3)->nullable()->comment('L/100 km ou L/h selon le mode de suivi');
            $table->decimal('moyenne_reference', 12, 3)->nullable()->comment('Moyenne du véhicule avant ce plein');
            $table->decimal('ecart_pourcentage', 8, 2)->nullable()->comment('Écart en % par rapport à la moyenne');
            $table->boolean('anomalie')->default(false)->comment('Plein signalé en rouge : > +30 % de la moyenne');

            $table->timestamps();

            $table->index(['vehicule_id', 'date_sortie', 'id']);
            $table->index('date_sortie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sorties');
    }
};
