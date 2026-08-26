<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel « Cuve » : saisi une seule fois (§3 du cahier des charges).
 *
 * Une cuve par carburant : on ne stocke pas du gasoil et de l'essence dans le
 * même réservoir, et un stock qui additionnerait les deux ne voudrait rien
 * dire. Le §3 parlait d'une cuve unique parce qu'il ne prévoyait qu'un
 * carburant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carburant_id')->unique()->constrained('carburants')->cascadeOnDelete();
            $table->string('nom')->default('Cuve principale');
            $table->decimal('capacite', 12, 2)->comment('Capacité en litres');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuves');
    }
};
