<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel « Véhicules et engins » (§3) :
 * code interne, désignation, carburant, mode de suivi (km ou heures),
 * capacité du réservoir.
 *
 * Le carburant du véhicule décide de la cuve dans laquelle ses pleins sont
 * décomptés : un moteur diesel ne prend pas d'essence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Code interne COVEC');
            $table->string('designation');
            $table->foreignId('carburant_id')->constrained('carburants')->restrictOnDelete();
            $table->string('mode_suivi', 10)->comment('km | heures');
            $table->decimal('capacite_reservoir', 10, 2)->comment('Capacité du réservoir en litres');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};
