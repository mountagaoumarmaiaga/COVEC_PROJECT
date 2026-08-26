<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Référentiel « Chauffeurs » (§3) : nom, matricule. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chauffeurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('matricule')->unique();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chauffeurs');
    }
};
