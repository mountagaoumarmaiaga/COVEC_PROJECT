<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes d'accès à l'application.
 *
 * Identification par matricule et non par adresse électronique : le matricule
 * est déjà la référence de l'entreprise, il se tape vite sur un poste de
 * station, et il n'oblige pas à donner une adresse professionnelle à chaque
 * pompiste. La contrepartie est qu'il n'y a pas de réinitialisation par
 * courriel — c'est le gestionnaire qui réattribue un mot de passe.
 *
 * À ne pas confondre avec la table `chauffeurs` : un chauffeur reçoit du
 * carburant, un utilisateur se sert de l'application. Les deux se recoupent
 * rarement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('matricule')->unique();
            $table->string('role', 20)->comment('pompiste | gestionnaire | consultation');
            $table->string('password');
            $table->boolean('actif')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
