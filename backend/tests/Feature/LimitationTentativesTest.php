<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Freinage des tentatives de connexion.
 *
 * Les matricules d'une station sont courts et devinables — ADMIN, POMPE-01,
 * DIR-01. Sans limite, un mot de passe de huit caractères tombe en quelques
 * heures d'essais automatisés, sans que rien dans les journaux ne distingue
 * l'attaque d'un agent qui tâtonne.
 */
class LimitationTentativesTest extends TestCase
{
    use RefreshDatabase;

    private function compte(string $matricule = 'ADMIN'): User
    {
        return User::query()->create([
            'nom' => 'Gestionnaire du dépôt',
            'matricule' => $matricule,
            'role' => Role::Gestionnaire,
            'password' => 'motdepasse1',
            'actif' => true,
        ]);
    }

    private function tenter(string $matricule, string $motDePasse): int
    {
        return $this->postJson('/api/connexion', [
            'matricule' => $matricule,
            'password' => $motDePasse,
        ])->getStatusCode();
    }

    public function test_les_tentatives_repetees_sur_un_compte_sont_bloquees(): void
    {
        $this->compte();

        // Cinq essais passent, le sixième est refusé.
        for ($i = 1; $i <= 5; $i++) {
            $this->assertSame(422, $this->tenter('ADMIN', "essai{$i}"), "Essai {$i} aurait dû être examiné.");
        }

        $this->assertSame(429, $this->tenter('ADMIN', 'essai6'));
    }

    public function test_le_blocage_vaut_aussi_pour_le_bon_mot_de_passe(): void
    {
        $this->compte();

        for ($i = 1; $i <= 5; $i++) {
            $this->tenter('ADMIN', "essai{$i}");
        }

        // Le frein ne sait pas distinguer l'attaquant du titulaire : c'est
        // volontaire, sans quoi il suffirait d'essayer le bon mot de passe
        // pour savoir qu'on l'a trouvé.
        $this->assertSame(429, $this->tenter('ADMIN', 'motdepasse1'));
    }

    public function test_le_message_de_blocage_est_en_francais(): void
    {
        $this->compte();

        for ($i = 1; $i <= 5; $i++) {
            $this->tenter('ADMIN', "essai{$i}");
        }

        $reponse = $this->postJson('/api/connexion', [
            'matricule' => 'ADMIN',
            'password' => 'essai6',
        ]);

        $reponse->assertStatus(429);
        $this->assertStringContainsString('Trop de tentatives', $reponse->json('message'));
    }

    public function test_le_balayage_de_plusieurs_matricules_est_aussi_freine(): void
    {
        // Un attaquant qui essaie le même mot de passe sur toute une liste de
        // matricules resterait sous la limite par compte. La seconde limite,
        // par adresse, l'arrête.
        for ($i = 1; $i <= 20; $i++) {
            $this->tenter("MATRICULE-{$i}", 'motdepasse-courant');
        }

        $this->assertSame(429, $this->tenter('MATRICULE-21', 'motdepasse-courant'));
    }

    public function test_le_changement_de_mot_de_passe_est_freine(): void
    {
        $compte = $this->compte();
        $this->actingAs($compte, 'sanctum');

        for ($i = 1; $i <= 6; $i++) {
            $this->putJson('/api/moi/mot-de-passe', [
                'actuel' => "essai{$i}",
                'nouveau' => 'nouveaumotdepasse',
                'nouveau_confirmation' => 'nouveaumotdepasse',
            ]);
        }

        $this->putJson('/api/moi/mot-de-passe', [
            'actuel' => 'motdepasse1',
            'nouveau' => 'nouveaumotdepasse',
            'nouveau_confirmation' => 'nouveaumotdepasse',
        ])->assertStatus(429);
    }
}
