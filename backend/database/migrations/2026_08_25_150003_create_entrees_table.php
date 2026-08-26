<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Écran 1 « Entrées » (§2) : remplissage de la cuve.
 * Date, fournisseur, quantité en litres, prix unitaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrees', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_entree')->comment('Horodatage de la réception');
            $table->foreignId('carburant_id')->constrained('carburants')->restrictOnDelete();
            $table->string('fournisseur');
            $table->decimal('quantite_litres', 12, 2);
            $table->decimal('prix_unitaire', 12, 2)->comment('Prix du litre');
            $table->string('reference_bon')->nullable()->comment('N° de bon de livraison');
            $table->timestamps();

            $table->index('date_entree');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrees');
    }
};
