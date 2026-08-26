<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des carburants distribués par la station.
 *
 * Le prix par défaut n'est pas le prix de chaque livraison : il pré-remplit la
 * saisie, et reste corrigeable livraison par livraison. Le prix du carburant
 * bouge, celui du bon de livraison fait foi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carburants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('gasoil | essence');
            $table->string('libelle');
            $table->decimal('prix_par_defaut', 12, 2)->comment('Prix du litre proposé à la saisie');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carburants');
    }
};
